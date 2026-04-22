<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class QueueServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(QueueManager::class, function () use ($app): QueueManager {
            $config = $app->config()->get('queue', []);
            if (!is_array($config)) {
                $config = [];
            }

            return new QueueManager($config, fn () => $app);
        });
    }
}
