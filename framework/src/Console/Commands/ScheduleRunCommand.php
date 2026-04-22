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
use Fnlla\Core\Application;
use Fnlla\Scheduler\ScheduleRegistry;
use Fnlla\Scheduler\Schedule;
use Fnlla\Support\Env;

final class ScheduleRunCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'schedule:run';
    }

    public function getDescription(): string
    {
        return 'Run scheduled tasks once.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $app = $this->bootstrapApp($root);

        $registry = null;
        if ($app instanceof Application && $app->has(ScheduleRegistry::class)) {
            $registry = $app->make(ScheduleRegistry::class);
        }

        if (!$registry instanceof ScheduleRegistry) {
            $registry = $this->makeRegistry($root);
        }

        foreach ($this->scheduleFiles($root) as $scheduleFile) {
            if (!is_file($scheduleFile)) {
                continue;
            }
            $loaded = require $scheduleFile;
            if (is_callable($loaded)) {
                $loaded($registry);
            }
            break;
        }

        $executed = $registry->runDue();
        $io->line('Ran ' . count($executed) . ' task(s).');
        return 0;
    }

    private function bootstrapApp(string $root): ?Application
    {
        if (getenv('APP_ROOT') === false) {
            putenv('APP_ROOT=' . $root);
        }

        $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        if (is_file($bootstrap)) {
            require $bootstrap;
        }

        $app = $GLOBALS['Fnlla_app'] ?? null;
        return $app instanceof Application ? $app : null;
    }

    private function makeRegistry(string $root): ScheduleRegistry
    {
        $configFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'scheduler.php';
        $config = [];
        if (is_file($configFile)) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        $timezone = (string) ($config['timezone'] ?? Env::get('APP_TIMEZONE', 'UTC'));
        $cachePath = (string) ($config['cache_path'] ?? Env::get('SCHEDULE_CACHE', 'storage/cache/schedule.json'));
        if ($cachePath !== '' && !str_starts_with($cachePath, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:\\\\#', $cachePath)) {
            $cachePath = $root . DIRECTORY_SEPARATOR . $cachePath;
        }

        return class_exists(Schedule::class)
            ? new Schedule(null, $cachePath, $timezone)
            : new ScheduleRegistry(null, $cachePath, $timezone);
    }

    private function scheduleFiles(string $root): array
    {
        return [
            $root . DIRECTORY_SEPARATOR . 'schedule.php',
            $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Schedule.php',
            $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'schedule.php',
        ];
    }
}
