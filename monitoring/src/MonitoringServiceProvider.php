<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Monitoring;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class MonitoringServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(MonitoringManager::class, function () use ($app): MonitoringManager {
            $config = $app->config()->get('monitoring', []);
            if (!is_array($config)) {
                $config = [];
            }
            $cache = $app->make(\Fnlla\Cache\CacheManager::class);
            return new MonitoringManager($cache, $config);
        });
    }
}
