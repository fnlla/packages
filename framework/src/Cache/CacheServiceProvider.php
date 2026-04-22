<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(CacheManager::class, function () use ($app): CacheManager {
            $config = $app->config()->get('cache', []);
            if (!is_array($config)) {
                $config = [];
            }

            $driver = strtolower((string) ($config['driver'] ?? 'file'));
            $ttl = $config['ttl'] ?? null;
            $path = (string) ($config['path'] ?? '');

            if ($path === '') {
                if (method_exists($app, 'basePath')) {
                    $path = rtrim((string) $app->basePath(), '/\\') . '/storage/cache';
                } else {
                    $path = getcwd() . '/storage/cache';
                }
            }

            $config['driver'] = $driver;
            $config['ttl'] = $ttl;
            $config['path'] = $path;

            return new CacheManager($config);
        });
    }
}
