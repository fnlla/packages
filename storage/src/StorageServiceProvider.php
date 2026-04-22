<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Storage;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(StorageManager::class, function () use ($app): StorageManager {
            $config = $app->config()->get('storage', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new StorageManager($config);
        });
    }
}
