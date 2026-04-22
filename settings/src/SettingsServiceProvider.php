<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Settings;

use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Support\ServiceProvider;

final class SettingsServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(SettingsRepository::class, function () use ($app): SettingsRepository {
            $config = $app->config()->get('settings', []);
            if (!is_array($config)) {
                $config = [];
            }
            $table = (string) ($config['table'] ?? 'settings');
            $connections = $app->make(ConnectionManager::class);
            return new SettingsRepository($connections, $table);
        });

        $app->singleton(SettingsStore::class, function () use ($app): SettingsStore {
            $repo = $app->make(SettingsRepository::class);
            return new SettingsStore($repo);
        });
    }

    public function boot(Container $app): void
    {
        $config = $app->config()->get('settings', []);
        if (!is_array($config)) {
            $config = [];
        }

        $auto = (bool) ($config['auto_migrate'] ?? false);
        if (!$auto) {
            return;
        }

        if (!$app->has(ConnectionManager::class)) {
            return;
        }

        $table = (string) ($config['table'] ?? 'settings');
        $connections = $app->make(ConnectionManager::class);
        SettingsSchema::ensure($connections->connection(), $table);
    }
}
