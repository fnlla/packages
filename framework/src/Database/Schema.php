<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

use PDO;
use RuntimeException;

final class Schema
{
    private static ?PDO $pdo = null;

    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function create(string $table, callable $callback): void
    {
        $t = new Table($table);
        $callback($t);
        self::executeCreate($t);
    }

    public static function dropIfExists(string $table): void
    {
        $pdo = self::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = 'DROP TABLE IF EXISTS ' . $table;

        if ($driver !== 'sqlite') {
            $pdo->exec($sql);
            return;
        }

        // SQLite can transiently lock tables; retry a few times before failing.
        $attempts = 3;
        for ($i = 0; $i < $attempts; $i++) {
            try {
                $pdo->exec($sql);
                return;
            } catch (\PDOException $e) {
                $message = $e->getMessage();
                if (str_contains($message, 'locked') || str_contains($message, 'SQLSTATE[HY000]: General error: 6')) {
                    usleep(50_000);
                    continue;
                }
                throw $e;
            }
        }

        $pdo->exec($sql);
    }

    public static function hasTable(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }

        $pdo = self::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
            $stmt->execute(['table' => $table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && ($row['name'] ?? '') === $table;
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    public static function hasIndex(string $table, string $index): bool
    {
        $table = trim($table);
        $index = trim($index);
        if ($table === '' || $index === '') {
            return false;
        }

        $pdo = self::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :index");
            $stmt->execute(['table' => $table, 'index' => $index]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && ($row['name'] ?? '') === $index;
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table AND indexname = :index');
            $stmt->execute(['table' => $table, 'index' => $index]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index'
        );
        $stmt->execute(['table' => $table, 'index' => $index]);
        return (bool) $stmt->fetchColumn();
    }

    private static function executeCreate(Table $table): void
    {
        $pdo = self::connection();
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $columnsSql = [];
        foreach ($table->columns() as $column) {
            $columnsSql[] = self::compileColumn($column, $driver);
        }

        $constraints = [];
        foreach ($table->foreignKeys() as $foreign) {
            if (!isset($foreign['column'], $foreign['references_table'])) {
                continue;
            }
            $referencesTable = (string) $foreign['references_table'];
            if ($referencesTable === '') {
                continue;
            }
            $fk = 'FOREIGN KEY (' . $foreign['column'] . ') REFERENCES '
                . $referencesTable . '(' . ($foreign['references_column'] ?? 'id') . ')';
            if (!empty($foreign['on_delete'])) {
                $fk .= ' ON DELETE ' . $foreign['on_delete'];
            }
            if (!empty($foreign['on_update'])) {
                $fk .= ' ON UPDATE ' . $foreign['on_update'];
            }
            $constraints[] = $fk;
        }

        $all = array_merge($columnsSql, $constraints);
        $sql = 'CREATE TABLE ' . $table->getName() . ' (' . implode(', ', $all) . ')';
        $pdo->exec($sql);

        foreach ($table->indexes() as $index) {
            self::createIndex($table->getName(), $index);
        }
    }

    private static function createIndex(string $table, array $index): void
    {
        $columns = $index['columns'] ?? [];
        if (!is_array($columns) || $columns === []) {
            return;
        }

        $type = $index['type'] ?? 'index';
        $name = $index['name'] ?? self::indexName($table, $columns, $type);
        $unique = $type === 'unique' ? 'UNIQUE ' : '';
        $sql = 'CREATE ' . $unique . 'INDEX ' . $name . ' ON ' . $table
            . ' (' . implode(', ', $columns) . ')';
        self::connection()->exec($sql);
    }

    private static function indexName(string $table, array $columns, string $type): string
    {
        $suffix = $type === 'unique' ? 'uniq' : 'idx';
        return $table . '_' . implode('_', $columns) . '_' . $suffix;
    }

    private static function compileColumn(ColumnDefinition $column, string $driver): string
    {
        $name = $column->getName();
        $type = $column->getType();
        $length = $column->getLength();

        $sqlType = match ($type) {
            'id' => self::idType($driver),
            'string' => 'VARCHAR(' . ($length ?? 255) . ')',
            'text' => 'TEXT',
            'integer', 'foreignId' => $driver === 'mysql' ? 'INT' : 'INTEGER',
            'boolean' => $driver === 'pgsql' ? 'BOOLEAN' : ($driver === 'mysql' ? 'TINYINT(1)' : 'INTEGER'),
            'datetime' => $driver === 'pgsql' ? 'TIMESTAMP' : ($driver === 'mysql' ? 'DATETIME' : 'TEXT'),
            'json' => ($driver === 'mysql' || $driver === 'pgsql') ? 'JSON' : 'TEXT',
            default => 'TEXT',
        };

        $parts = [$name . ' ' . $sqlType];

        if ($type === 'id') {
            $parts[] = self::idModifiers($driver);
        } else {
            if ($column->isAutoIncrement()) {
                if ($driver === 'mysql') {
                    $parts[] = 'AUTO_INCREMENT';
                } elseif ($driver === 'pgsql') {
                    $parts[0] = $name . ' BIGSERIAL';
                } elseif ($driver === 'sqlite') {
                    $parts[0] = $name . ' INTEGER';
                    $parts[] = 'PRIMARY KEY AUTOINCREMENT';
                }
            }
            if ($column->isPrimary()) {
                $parts[] = 'PRIMARY KEY';
            }
        }

        $parts[] = $column->isNullable() ? 'NULL' : 'NOT NULL';

        if ($column->hasDefault()) {
            $parts[] = 'DEFAULT ' . self::quoteDefault($column->getDefault());
        }

        return trim(implode(' ', array_filter($parts, fn ($item) => $item !== '')));
    }

    private static function idType(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'BIGSERIAL',
            'sqlite' => 'INTEGER',
            default => 'BIGINT',
        };
    }

    private static function idModifiers(string $driver): string
    {
        return match ($driver) {
            'mysql' => 'AUTO_INCREMENT PRIMARY KEY',
            'sqlite' => 'PRIMARY KEY AUTOINCREMENT',
            default => 'PRIMARY KEY',
        };
    }

    private static function quoteDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        $escaped = str_replace("'", "''", (string) $value);
        return "'" . $escaped . "'";
    }

    private static function connection(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Schema connection is not configured. Call Schema::setConnection($pdo).');
        }
        return self::$pdo;
    }
}
