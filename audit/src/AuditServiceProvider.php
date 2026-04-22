<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(AuditRepository::class, function () use ($app): AuditRepository {
            $config = $app->config()->get('audit', []);
            if (!is_array($config)) {
                $config = [];
            }
            $table = (string) ($config['table'] ?? 'audit_log');
            $connections = $app->make(ConnectionManager::class);
            return new AuditRepository($connections, $table);
        });

        $app->singleton(AuditLogger::class, function () use ($app): AuditLogger {
            $context = new ServerAuditContext();
            $authClass = 'Fnlla\\Auth\\AuthManager';
            if (class_exists($authClass) && $app->has($authClass)) {
                $auth = $app->make($authClass);
                if (is_object($auth)) {
                    $context = new AuthAuditContext($auth, $context);
                }
            }

            $repo = $app->make(AuditRepository::class);
            return new AuditLogger($repo, $context);
        });
    }

    public function boot(Container $app): void
    {
        $config = $app->config()->get('audit', []);
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

        $table = (string) ($config['table'] ?? 'audit_log');
        $connections = $app->make(ConnectionManager::class);
        AuditSchema::ensure($connections->connection(), $table);
    }
}




