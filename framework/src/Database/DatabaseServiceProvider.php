<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class DatabaseServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(ConnectionManager::class, function () use ($app): ConnectionManager {
            $config = $app->config()->get('database', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new ConnectionManager($config);
        });

        $app->singleton(TransactionManager::class, function () use ($app): TransactionManager {
            return new TransactionManager($app->make(ConnectionManager::class));
        });
    }
}
