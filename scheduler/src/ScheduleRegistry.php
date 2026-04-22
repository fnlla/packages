<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Fnlla\Core\Container;
use RuntimeException;

class ScheduleRegistry
{
    private array $tasks = [];
    private ScheduleStore $store;
    private DateTimeZone $timezone;

    public function __construct(
        private ?Container $app = null,
        ?string $cachePath = null,
        string $timezone = 'UTC'
    ) {
        $path = $cachePath ?: (getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'schedule.json');
        $this->store = new ScheduleStore($path);
        $this->timezone = new DateTimeZone($timezone === '' ? 'UTC' : $timezone);
    }

    public function command(string $command): ScheduleTask
    {
        $command = trim($command);
        if ($command === '') {
            throw new RuntimeException('Schedule command cannot be empty.');
        }

        $name = 'command:' . preg_replace('/\s+/', '_', $command);

        $handler = function () use ($command): int {
            if (!class_exists(\Fnlla\Console\ConsoleApplication::class)) {
                throw new RuntimeException('Console package is required to run scheduled commands.');
            }

            $root = $this->resolveRoot();
            $cli = new \Fnlla\Console\ConsoleApplication($root);
            $parts = preg_split('/\s+/', $command) ?: [];
            $argv = array_merge(['Fnlla'], array_values($parts));
            return $cli->run($argv);
        };

        return $this->call($name, $handler);
    }

    public function call(string $name, callable $handler): ScheduleTask
    {
        $task = new ScheduleTask($name, $handler);
        $this->tasks[$name] = $task;
        return $task;
    }

    public function tasks(): array
    {
        return $this->tasks;
    }

    public function runDue(?DateTimeImmutable $now = null): array
    {
        $now = $now ?? new DateTimeImmutable('now', $this->timezone);
        $timestamp = $now->getTimestamp();
        $lastRuns = $this->store->load();

        $executed = [];

        foreach ($this->tasks as $name => $task) {
            $lastRun = isset($lastRuns[$name]) ? (int) $lastRuns[$name] : null;
            if (!$task->isDue($now, $lastRun)) {
                continue;
            }

            $this->invoke($task);
            $lastRuns[$name] = $timestamp;
            $executed[] = $name;
        }

        if ($executed !== []) {
            $this->store->save($lastRuns);
        }

        return $executed;
    }

    private function invoke(ScheduleTask $task): mixed
    {
        $handler = $task->handler();

        if ($task->runsInBackground() && $this->app instanceof Container) {
            if (class_exists(\Fnlla\Queue\QueueManager::class)
                && interface_exists(\Fnlla\Queue\JobInterface::class)
                && $this->app->has(\Fnlla\Queue\QueueManager::class)
            ) {
                $queue = $this->app->make(\Fnlla\Queue\QueueManager::class);
                if ($queue instanceof \Fnlla\Queue\QueueManager) {
                    $queue->dispatch(new class($handler) implements \Fnlla\Queue\JobInterface {
                        /** @var callable */
                        private $handler;

                        public function __construct(callable $handler)
                        {
                            $this->handler = $handler;
                        }

                        public function handle(Container $app): void
                        {
                            $app->call($this->handler);
                        }
                    });
                    return null;
                }
            }
        }

        if ($this->app instanceof Container) {
            return $this->app->call($handler);
        }

        return $handler();
    }

    private function resolveRoot(): string
    {
        if ($this->app instanceof Container && method_exists($this->app, 'basePath')) {
            try {
                $root = (string) $this->app->basePath();
                if ($root !== '') {
                    return $root;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return getcwd() ?: '.';
    }
}
