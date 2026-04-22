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
use Fnlla\Support\Env;

final class MigrationRunner
{
    private string $table = 'migrations';

    public function __construct(
        private ConnectionManager $connections,
        private ?string $path = null
    ) {
    }

    public function migrate(): array
    {
        $pdo = $this->connections->connection();
        if (class_exists(\Fnlla\Database\Schema::class)) {
            \Fnlla\Database\Schema::setConnection($pdo);
        }
        $this->ensureRepository($pdo);

        $path = $this->migrationPath();
        $files = $this->migrationFiles($path);
        $ran = $this->getRan($pdo);
        $batch = $this->nextBatch($pdo);

        $executed = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) {
                continue;
            }
            $migration = $this->resolveMigration($file);
            if (class_exists(\Fnlla\Database\Schema::class)) {
                \Fnlla\Database\Schema::setConnection($pdo);
            }
            $migration->up($pdo);
            $this->logMigration($pdo, $name, $batch);
            $executed[] = $name;
        }

        return $executed;
    }

    public function rollback(int $steps = 1): array
    {
        $pdo = $this->connections->connection();
        if (class_exists(\Fnlla\Database\Schema::class)) {
            \Fnlla\Database\Schema::setConnection($pdo);
        }
        $this->ensureRepository($pdo);

        $path = $this->migrationPath();
        $batches = $this->getLastBatches($pdo, $steps);
        if ($batches === []) {
            return [];
        }

        $rolled = [];
        foreach ($batches as $migration) {
            $file = $path . DIRECTORY_SEPARATOR . $migration['migration'];
            if (!is_file($file)) {
                continue;
            }
            $instance = $this->resolveMigration($file);
            if (class_exists(\Fnlla\Database\Schema::class)) {
                \Fnlla\Database\Schema::setConnection($pdo);
            }
            $instance->down($pdo);
            $this->deleteMigration($pdo, $migration['migration']);
            $rolled[] = $migration['migration'];
        }

        return $rolled;
    }

    public function status(): array
    {
        $pdo = $this->connections->connection();
        $this->ensureRepository($pdo);

        $path = $this->migrationPath();
        $files = $this->migrationFiles($path);
        $ran = $this->getRan($pdo);
        $status = [];

        foreach ($files as $file) {
            $name = basename($file);
            $status[] = [
                'migration' => $name,
                'ran' => in_array($name, $ran, true),
            ];
        }

        return $status;
    }

    private function migrationPath(): string
    {
        if ($this->path !== null && $this->path !== '') {
            return $this->path;
        }

        $configured = $this->env('MIGRATIONS_PATH');
        if ($configured !== '') {
            return $configured;
        }

        $appRoot = $this->env('APP_ROOT');
        if ($appRoot !== '') {
            return rtrim($appRoot, '/\\') . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        }

        return getcwd() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    private function migrationFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files);
        return $files;
    }

    private function resolveMigration(string $file): MigrationInterface
    {
        $migration = require $file;
        if ($migration instanceof MigrationInterface) {
            return $migration;
        }

        if (is_string($migration) && class_exists($migration)) {
            $instance = new $migration();
            if ($instance instanceof MigrationInterface) {
                return $instance;
            }
        }

        throw new RuntimeException('Invalid migration file: ' . $file);
    }

    private function ensureRepository(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id SERIAL PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL, migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)';
        } elseif ($driver === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL, batch INTEGER NOT NULL, migrated_at TEXT DEFAULT CURRENT_TIMESTAMP)';
        } else {
            $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id INT AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL, batch INT NOT NULL, migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)';
        }

        $pdo->exec($sql);
    }

    private function getRan(PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT migration FROM ' . $this->table);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }
        return array_map('strval', $rows);
    }

    private function nextBatch(PDO $pdo): int
    {
        $stmt = $pdo->query('SELECT MAX(batch) FROM ' . $this->table);
        $max = $stmt ? (int) $stmt->fetchColumn() : 0;
        if ($stmt) {
            $stmt->closeCursor();
        }
        return $max + 1;
    }

    private function logMigration(PDO $pdo, string $migration, int $batch): void
    {
        $stmt = $pdo->prepare('INSERT INTO ' . $this->table . ' (migration, batch) VALUES (?, ?)');
        $stmt->execute([$migration, $batch]);
    }

    private function deleteMigration(PDO $pdo, string $migration): void
    {
        $stmt = $pdo->prepare('DELETE FROM ' . $this->table . ' WHERE migration = ?');
        $stmt->execute([$migration]);
    }

    private function getLastBatches(PDO $pdo, int $steps): array
    {
        $stmt = $pdo->query('SELECT migration, batch FROM ' . $this->table . ' ORDER BY batch DESC, id DESC');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if ($stmt) {
            $stmt->closeCursor();
        }
        if ($rows === []) {
            return [];
        }

        $targetBatches = [];
        foreach ($rows as $row) {
            $batch = (int) $row['batch'];
            if (!in_array($batch, $targetBatches, true)) {
                $targetBatches[] = $batch;
            }
            if (count($targetBatches) >= $steps) {
                break;
            }
        }

        return array_values(array_filter($rows, fn ($row) => in_array((int) $row['batch'], $targetBatches, true)));
    }

    private function env(string $key, mixed $default = ''): string
    {
        return (string) Env::get($key, $default);
    }
}
