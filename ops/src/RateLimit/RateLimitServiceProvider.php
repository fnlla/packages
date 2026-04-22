<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\RateLimit;

use Fnlla\Cache\CacheManager;
use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(RateLimiter::class, function () use ($app): RateLimiter {
            $cache = $app->make(CacheManager::class);
            return new RateLimiter($cache);
        });
    }
}
