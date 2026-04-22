<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Sentry;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class SentryServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(SentryManager::class, function () use ($app): SentryManager {
            $config = $app->config()->get('sentry', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new SentryManager($config);
        });
    }

    public function boot(Container $app): void
    {
        $manager = $app->make(SentryManager::class);
        if ($manager instanceof SentryManager) {
            $manager->init();
        }
    }
}
