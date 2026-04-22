<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;


use Fnlla\Core\ServiceProvider;
use Fnlla\Contracts\Queue\QueueInterface;

final class QueueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(QueueInterface::class, function (): QueueInterface {
            $config = $this->app->config()->get('queue', []);
            if (!is_array($config)) {
                $config = [];
            }

            $driver = strtolower((string) ($config['driver'] ?? 'sync'));
            if ($driver !== '' && class_exists($driver)) {
                $instance = $this->app->make($driver);
                if ($instance instanceof QueueInterface) {
                    return $instance;
                }
            }

            return new SyncQueue($this->app);
        });
        $this->app->singleton(Queue::class, fn () => $this->app->make(QueueInterface::class));
    }
}







