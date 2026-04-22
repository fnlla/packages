<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Database\ConnectionManager;
use Closure;
use PDO;
use Throwable;

final class DatabaseQueue implements QueueDriverInterface
{
    private bool $tablesEnsured = false;
    private Closure $clock;

    public function __construct(
        private ConnectionManager $connections,
        private string $table = 'queue_jobs',
        private string $failedTable = 'queue_failed_jobs',
        private int $defaultMaxAttempts = 3,
        private int $retryAfter = 60,
        private string $payloadSecret = '',
        private array $allowedJobClasses = [],
        ?callable $clock = null
    ) {
        $this->defaultMaxAttempts = max(1, $this->defaultMaxAttempts);
        $this->retryAfter = max(1, $this->retryAfter);
        $this->allowedJobClasses = $this->normalizeAllowedJobClasses($this->allowedJobClasses);
        $this->clock = $clock !== null ? Closure::fromCallable($clock) : static fn (): int => time();
    }

    public function dispatch(JobInterface $job): void
    {
        $pdo = $this->connections->connection();
        $this->ensureTables($pdo);

        $payload = serialize($job);
        $jobClass = get_class($job);
        $signature = $this->signPayload($payload);
        $now = $this->now();
        $maxAttempts = $this->defaultMaxAttempts;

        $stmt = $pdo->prepare(
            'INSERT INTO ' . $this->table . ' (payload, job_class, signature, attempts, max_attempts, available_at, created_at)'
            . ' VALUES (?, ?, ?, 0, ?, ?, ?)'
        );
        $stmt->execute([$payload, $jobClass, $signature, $maxAttempts, $now, $now]);
    }

    public function pop(?int $retryAfter = null): ?QueuedJob
    {
        $pdo = $this->connections->connection();
        $this->ensureTables($pdo);

        $now = $this->now();
        $retryAfter = $retryAfter !== null ? max(1, $retryAfter) : $this->retryAfter;
        $expired = $now - $retryAfter;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, payload, job_class, signature, attempts, max_attempts FROM ' . $this->table
                . ' WHERE available_at <= ? AND (reserved_at IS NULL OR reserved_at <= ?)'
                . ' ORDER BY id ASC LIMIT 1'
            );
            $stmt->execute([$now, $expired]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $pdo->commit();
                return null;
            }

            $update = $pdo->prepare(
                'UPDATE ' . $this->table
                . ' SET reserved_at = ?, attempts = attempts + 1 WHERE id = ? AND (reserved_at IS NULL OR reserved_at <= ?)'
            );
            $update->execute([$now, $row['id'], $expired]);

            if ($update->rowCount() !== 1) {
                $pdo->commit();
                return null;
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $attempts = (int) ($row['attempts'] ?? 0) + 1;
        $maxAttempts = (int) ($row['max_attempts'] ?? $this->defaultMaxAttempts);
        return new QueuedJob(
            (string) $row['id'],
            (string) $row['payload'],
            (string) ($row['job_class'] ?? ''),
            (string) ($row['signature'] ?? ''),
            $attempts,
            $maxAttempts
        );
    }

    public function delete(int|string $id): void
    {
        $pdo = $this->connections->connection();
        $stmt = $pdo->prepare('DELETE FROM ' . $this->table . ' WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function release(int|string $id, int $delaySeconds = 0): void
    {
        $pdo = $this->connections->connection();
        $available = $this->now() + max(0, $delaySeconds);
        $stmt = $pdo->prepare(
            'UPDATE ' . $this->table . ' SET reserved_at = NULL, available_at = ? WHERE id = ?'
        );
        $stmt->execute([$available, $id]);
    }

    public function fail(int|string $id, string $payload, ?Throwable $error = null): void
    {
        $pdo = $this->connections->connection();
        $this->ensureTables($pdo);

        $message = $error ? $error->getMessage() : 'Job failed';
        $trace = $error ? $error->getTraceAsString() : null;
        $now = $this->now();

        $stmt = $pdo->prepare(
            'INSERT INTO ' . $this->failedTable . ' (payload, error, trace, failed_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$payload, $message, $trace, $now]);

        $this->delete($id);
    }

    public function table(): string
    {
        return $this->table;
    }

    public function failedTable(): string
    {
        return $this->failedTable;
    }

    public function defaultMaxAttempts(): int
    {
        return $this->defaultMaxAttempts;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    public function validate(QueuedJob $job): ?string
    {
        if ($job->jobClass() === '') {
            return 'Queued job class is missing.';
        }

        $expected = $this->signPayload($job->payload());
        if (!hash_equals($expected, $job->signature())) {
            return 'Invalid queued job signature.';
        }

        if ($this->allowedJobClasses !== ['*']) {
            $jobClass = $job->jobClass();
            if (!in_array($jobClass, $this->allowedJobClasses, true)) {
                return 'Queued job class is not allowed: ' . $jobClass;
            }
        }

        return null;
    }

    private function ensureTables(PDO $pdo): void
    {
        if ($this->tablesEnsured) {
            return;
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $jobsSql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id BIGSERIAL PRIMARY KEY, payload TEXT NOT NULL, job_class TEXT NOT NULL DEFAULT \'\','
                . ' signature TEXT NOT NULL DEFAULT \'\', attempts INT NOT NULL DEFAULT 0,'
                . ' max_attempts INT NOT NULL DEFAULT ' . $this->defaultMaxAttempts . ','
                . ' available_at BIGINT NOT NULL, reserved_at BIGINT NULL, created_at BIGINT NOT NULL)';
            $failedSql = 'CREATE TABLE IF NOT EXISTS ' . $this->failedTable
                . ' (id BIGSERIAL PRIMARY KEY, payload TEXT NOT NULL, error TEXT NOT NULL, trace TEXT NULL, failed_at BIGINT NOT NULL)';
        } elseif ($driver === 'sqlite') {
            $jobsSql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, payload TEXT NOT NULL, job_class TEXT NOT NULL DEFAULT \'\','
                . ' signature TEXT NOT NULL DEFAULT \'\', attempts INTEGER NOT NULL DEFAULT 0,'
                . ' max_attempts INTEGER NOT NULL DEFAULT ' . $this->defaultMaxAttempts . ','
                . ' available_at INTEGER NOT NULL, reserved_at INTEGER NULL, created_at INTEGER NOT NULL)';
            $failedSql = 'CREATE TABLE IF NOT EXISTS ' . $this->failedTable
                . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, payload TEXT NOT NULL, error TEXT NOT NULL, trace TEXT NULL, failed_at INTEGER NOT NULL)';
        } else {
            $jobsSql = 'CREATE TABLE IF NOT EXISTS ' . $this->table
                . ' (id BIGINT AUTO_INCREMENT PRIMARY KEY, payload LONGTEXT NOT NULL, job_class VARCHAR(255) NOT NULL DEFAULT \'\','
                . ' signature TEXT NOT NULL DEFAULT \'\', attempts INT NOT NULL DEFAULT 0,'
                . ' max_attempts INT NOT NULL DEFAULT ' . $this->defaultMaxAttempts . ','
                . ' available_at BIGINT NOT NULL, reserved_at BIGINT NULL, created_at BIGINT NOT NULL)';
            $failedSql = 'CREATE TABLE IF NOT EXISTS ' . $this->failedTable
                . ' (id BIGINT AUTO_INCREMENT PRIMARY KEY, payload LONGTEXT NOT NULL, error TEXT NOT NULL, trace LONGTEXT NULL, failed_at BIGINT NOT NULL)';
        }

        $pdo->exec($jobsSql);
        $pdo->exec($failedSql);
        $this->ensureColumns($pdo, $driver);
        $this->tablesEnsured = true;
    }

    private function ensureColumns(PDO $pdo, string $driver): void
    {
        $jobColumns = $this->getColumns($pdo, $driver, $this->table);
        $failedColumns = $this->getColumns($pdo, $driver, $this->failedTable);

        $jobType = $driver === 'pgsql' ? 'BIGINT' : ($driver === 'sqlite' ? 'INTEGER' : 'BIGINT');
        $intType = $driver === 'pgsql' ? 'INT' : ($driver === 'sqlite' ? 'INTEGER' : 'INT');
        $textType = $driver === 'sqlite' ? 'TEXT' : ($driver === 'pgsql' ? 'TEXT' : 'LONGTEXT');
        $shortTextType = $driver === 'sqlite' ? 'TEXT' : ($driver === 'pgsql' ? 'TEXT' : 'VARCHAR(255)');

        $this->addColumnIfMissing($pdo, $driver, $this->table, 'job_class', $shortTextType . " NOT NULL DEFAULT ''", $jobColumns);
        $this->addColumnIfMissing($pdo, $driver, $this->table, 'signature', $textType . " NOT NULL DEFAULT ''", $jobColumns);
        $this->addColumnIfMissing($pdo, $driver, $this->table, 'attempts', $intType . ' NOT NULL DEFAULT 0', $jobColumns);
        $this->addColumnIfMissing(
            $pdo,
            $driver,
            $this->table,
            'max_attempts',
            $intType . ' NOT NULL DEFAULT ' . $this->defaultMaxAttempts,
            $jobColumns
        );
        $this->addColumnIfMissing($pdo, $driver, $this->table, 'available_at', $jobType . ' NOT NULL DEFAULT 0', $jobColumns);
        $this->addColumnIfMissing($pdo, $driver, $this->table, 'reserved_at', $jobType . ' NULL', $jobColumns);

        $this->addColumnIfMissing($pdo, $driver, $this->failedTable, 'trace', $textType . ' NULL', $failedColumns);
    }

    private function getColumns(PDO $pdo, string $driver, string $table): array
    {
        if ($driver === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn ($row) => (string) $row['name'], $rows ?: []);
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare(
                'SELECT column_name FROM information_schema.columns WHERE table_name = ?'
            );
            $stmt->execute([$table]);
            return array_map(static fn ($row) => (string) $row['column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        $rows = $pdo->query('SHOW COLUMNS FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn ($row) => (string) $row['Field'], $rows ?: []);
    }

    private function addColumnIfMissing(PDO $pdo, string $driver, string $table, string $column, string $definition, array $columns): void
    {
        if (in_array($column, $columns, true)) {
            return;
        }

        $sql = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition;
        $pdo->exec($sql);
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    private function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->payloadSecret);
    }

    private function normalizeAllowedJobClasses(array $allowed): array
    {
        if ($allowed === []) {
            return ['*'];
        }

        $list = [];
        foreach ($allowed as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $list[] = $item;
        }

        if ($list === []) {
            return ['*'];
        }

        if (in_array('*', $list, true)) {
            return ['*'];
        }

        return array_values(array_unique($list));
    }
}
