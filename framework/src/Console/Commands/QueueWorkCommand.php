<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;
use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Queue\QueueDriverInterface;
use Fnlla\Queue\QueueManager;
use Fnlla\Queue\QueueWorker;

final class QueueWorkCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Process queued jobs (database or redis driver).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $app = $this->bootstrapApp($root);
        $container = $app instanceof Container ? $app : $this->buildContainer($root);
        if (!$container instanceof Container) {
            $io->error('Unable to bootstrap application.');
            return 1;
        }

        if (!class_exists(QueueManager::class)) {
            $io->error('QueueManager is not available. Require fnlla/queue.');
            return 1;
        }

        $manager = $this->resolveManager($container, $root);
        if (!$manager instanceof QueueManager) {
            $io->error('QueueManager binding is invalid.');
            return 1;
        }

        $queue = $manager->queue();
        if (!$queue instanceof QueueDriverInterface) {
            $io->error('queue:work requires a queue driver that supports workers (database or redis).');
            return 1;
        }

        $maxJobs = (int) ($options['max'] ?? 0);
        $sleep = (int) ($options['sleep'] ?? 1);
        if (!empty($options['once'])) {
            $maxJobs = 1;
        }

        $queueConfig = $this->loadConfig($root, 'queue.php');
        $driver = strtolower((string) ($queueConfig['driver'] ?? 'sync'));
        $driverConfig = [];
        if ($driver === 'database') {
            $driverConfig = is_array($queueConfig['database'] ?? null) ? $queueConfig['database'] : [];
        } elseif ($driver === 'redis') {
            $driverConfig = is_array($queueConfig['redis'] ?? null) ? $queueConfig['redis'] : [];
        }

        $tries = isset($options['tries'])
            ? (int) $options['tries']
            : (int) ($driverConfig['max_attempts'] ?? $driverConfig['tries'] ?? 0);
        $retryAfter = isset($options['retry-after'])
            ? (int) $options['retry-after']
            : (int) ($driverConfig['retry_after'] ?? 0);
        $backoff = $this->parseBackoff($options['backoff'] ?? ($driverConfig['backoff'] ?? null));

        if ($tries <= 0) {
            $tries = null;
        }
        if ($retryAfter <= 0) {
            $retryAfter = null;
        }

        $worker = new QueueWorker($queue, $container, $tries, $backoff, $retryAfter);
        $processed = $worker->work($maxJobs, $sleep);

        $io->line('Processed ' . $processed . ' job(s).');
        return 0;
    }

    private function bootstrapApp(string $root): ?Container
    {
        if (getenv('APP_ROOT') === false) {
            putenv('APP_ROOT=' . $root);
        }

        $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        if (is_file($bootstrap)) {
            require $bootstrap;
        }

        $app = $GLOBALS['Fnlla_app'] ?? null;
        return $app instanceof Container ? $app : null;
    }

    private function buildContainer(string $root): Container
    {
        $container = new Container();

        $queueConfig = $this->loadConfig($root, 'queue.php');
        $dbConfig = $this->loadConfig($root, 'database.php');

        if (!is_array($queueConfig)) {
            $queueConfig = [];
        }
        if (!is_array($dbConfig)) {
            $dbConfig = [];
        }

        $container->instance(ConnectionManager::class, new ConnectionManager($dbConfig));
        $container->instance(QueueManager::class, new QueueManager($queueConfig, fn () => $container));

        return $container;
    }

    private function resolveManager(Container $container, string $root): QueueManager
    {
        if ($container->has(QueueManager::class)) {
            $manager = $container->make(QueueManager::class);
            if ($manager instanceof QueueManager) {
                return $manager;
            }
        }

        $queueConfig = $this->loadConfig($root, 'queue.php');
        if (!is_array($queueConfig)) {
            $queueConfig = [];
        }

        $manager = new QueueManager($queueConfig, fn () => $container);
        $container->instance(QueueManager::class, $manager);
        return $manager;
    }

    private function loadConfig(string $root, string $file): array
    {
        $path = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            return [];
        }

        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }

    private function parseBackoff(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            $values = [];
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                if (is_numeric($part)) {
                    $values[] = (int) $part;
                }
            }
            return $values;
        }

        return [];
    }
}
