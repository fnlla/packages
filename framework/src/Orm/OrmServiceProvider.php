<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm;

use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Support\ServiceProvider;

final class OrmServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        Model::setContainer($app);
    }

    public function boot(Container $app): void
    {
        if ($app->has(ConnectionManager::class)) {
            $manager = $app->make(ConnectionManager::class);
            if ($manager instanceof ConnectionManager) {
                Model::setConnectionManager($manager);
            }
        }
    }
}
