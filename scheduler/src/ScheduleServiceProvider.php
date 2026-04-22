<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Scheduler;

use Fnlla\Core\Container;
use Fnlla\Support\Env;
use Fnlla\Support\ServiceProvider;

final class ScheduleServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(ScheduleRegistry::class, function () use ($app): ScheduleRegistry {
            $config = $app->config()->get('scheduler', []);
            if (!is_array($config)) {
                $config = [];
            }

            $timezone = (string) ($config['timezone'] ?? Env::get('APP_TIMEZONE', 'UTC'));
            $cachePath = (string) ($config['cache_path'] ?? Env::get('SCHEDULE_CACHE', 'storage/cache/schedule.json'));

            if ($cachePath !== '' && !str_starts_with($cachePath, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:\\\\#', $cachePath)) {
                $base = method_exists($app, 'basePath') ? (string) $app->basePath() : getcwd();
                $cachePath = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . $cachePath;
            }

            return new Schedule($app, $cachePath, $timezone);
        });

        $app->singleton(Schedule::class, fn () => $app->make(ScheduleRegistry::class));
    }
}
