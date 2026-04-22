<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Closure;
use RuntimeException;

final class QueueManager
{
    private ?QueueInterface $queue = null;
    private ?Closure $containerResolver = null;

    public function __construct(private array $config, ?callable $containerResolver = null)
    {
        if ($containerResolver !== null) {
            $this->containerResolver = Closure::fromCallable($containerResolver);
        }
    }

    public function queue(): QueueInterface
    {
        if ($this->queue !== null) {
            return $this->queue;
        }

        $driver = strtolower((string) ($this->config['driver'] ?? 'sync'));

        if ($driver === 'sync') {
            $container = $this->resolveContainer();
            $this->queue = new SyncQueue($container);
            return $this->queue;
        }

        if ($driver === 'database') {
            $container = $this->resolveContainer();

            if (!class_exists(\Fnlla\Database\ConnectionManager::class)) {
                throw new RuntimeException('Database queue driver requires the core Database module.');
            }

            $connections = $container->make(\Fnlla\Database\ConnectionManager::class);
            if (!$connections instanceof \Fnlla\Database\ConnectionManager) {
                throw new RuntimeException('ConnectionManager is not available for database queue driver.');
            }

            $databaseConfig = $this->config['database'] ?? [];
            if (!is_array($databaseConfig)) {
                $databaseConfig = [];
            }

            $table = (string) ($databaseConfig['table'] ?? 'queue_jobs');
            $failedTable = (string) ($databaseConfig['failed_table'] ?? 'queue_failed_jobs');
            $defaultMaxAttempts = (int) ($databaseConfig['max_attempts'] ?? $databaseConfig['tries'] ?? 3);
            $retryAfter = (int) ($databaseConfig['retry_after'] ?? 60);
            $payloadSecret = (string) ($databaseConfig['payload_secret'] ?? $this->config['payload_secret'] ?? '');
            $allowed = $databaseConfig['allowed_job_classes'] ?? $this->config['allowed_job_classes'] ?? [];
            $allowed = $this->normalizeAllowedJobClasses($allowed);

            $this->queue = new DatabaseQueue(
                $connections,
                $table,
                $failedTable,
                $defaultMaxAttempts,
                $retryAfter,
                $payloadSecret,
                $allowed
            );
            return $this->queue;
        }

        if ($driver === 'redis') {
            $redisConfig = $this->config['redis'] ?? [];
            if (!is_array($redisConfig)) {
                $redisConfig = [];
            }

            $queue = (string) ($redisConfig['queue'] ?? $this->config['queue'] ?? 'default');
            $defaultMaxAttempts = (int) ($redisConfig['max_attempts'] ?? $redisConfig['tries'] ?? 3);
            $retryAfter = (int) ($redisConfig['retry_after'] ?? 60);
            $payloadSecret = (string) ($redisConfig['payload_secret'] ?? $this->config['payload_secret'] ?? '');
            $allowed = $redisConfig['allowed_job_classes'] ?? $this->config['allowed_job_classes'] ?? [];
            $allowed = $this->normalizeAllowedJobClasses($allowed);

            $this->queue = new RedisQueue(
                $redisConfig,
                $queue,
                $defaultMaxAttempts,
                $retryAfter,
                $payloadSecret,
                $allowed
            );
            return $this->queue;
        }

        throw new RuntimeException('Unsupported queue driver: ' . $driver);
    }

    public function dispatch(JobInterface $job): void
    {
        $this->queue()->dispatch($job);
    }

    public function setQueue(QueueInterface $queue): void
    {
        $this->queue = $queue;
    }

    private function resolveContainer(): \Fnlla\Core\Container
    {
        if ($this->containerResolver !== null) {
            $container = ($this->containerResolver)();
            if ($container instanceof \Fnlla\Core\Container) {
                return $container;
            }
        }

        throw new RuntimeException('QueueManager requires a Container instance.');
    }

    private function normalizeAllowedJobClasses(mixed $allowed): array
    {
        if (is_string($allowed)) {
            $allowed = trim($allowed);
            if ($allowed === '' || $allowed === '*') {
                return ['*'];
            }
            $parts = array_map('trim', explode(',', $allowed));
            $allowed = array_filter($parts, static fn ($value) => $value !== '');
        }

        if (!is_array($allowed)) {
            return ['*'];
        }

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

        if ($list === [] || in_array('*', $list, true)) {
            return ['*'];
        }

        return array_values(array_unique($list));
    }
}
