<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;


use Fnlla\Core\ServiceProvider;
use Fnlla\Contracts\Cache\CacheStoreInterface as CacheStoreContract;
use Fnlla\Support\Psr\Cache\CacheItemPoolInterface;
use Fnlla\Support\Psr\SimpleCache\CacheInterface;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheStoreContract::class, function (): CacheStoreContract {
            $config = $this->app->config()->get('cache', []);
            if (!is_array($config)) {
                $config = [];
            }

            $driver = strtolower((string) ($config['driver'] ?? 'file'));
            $path = (string) ($config['path'] ?? '');
            if ($path === '') {
                $path = $this->app->basePath() . '/storage/cache';
            }

            return match ($driver) {
                'array' => new ArrayStore(),
                default => new FileStore($path),
            };
        });

        $this->app->singleton(CacheStoreInterface::class, fn (): CacheStoreContract => $this->app->make(CacheStoreContract::class));

        $this->app->singleton(Cache::class, function (): Cache {
            return new Cache($this->app->make(CacheStoreContract::class));
        });

        $this->app->singleton(CacheInterface::class, function (): SimpleCacheAdapter {
            $config = $this->app->config()->get('cache', []);
            if (!is_array($config)) {
                $config = [];
            }
            $prefix = (string) ($config['prefix'] ?? '');
            return new SimpleCacheAdapter($this->app->make(Cache::class)->store(), $prefix);
        });

        $this->app->singleton(CacheItemPoolInterface::class, function (): CacheItemPool {
            $config = $this->app->config()->get('cache', []);
            if (!is_array($config)) {
                $config = [];
            }
            $prefix = (string) ($config['psr6_prefix'] ?? 'psr6:');
            return new CacheItemPool($this->app->make(Cache::class)->store(), $prefix);
        });
    }
}








