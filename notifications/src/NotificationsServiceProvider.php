<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications;

use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Mail\MailerInterface;
use Fnlla\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(SmsSenderInterface::class, fn (): SmsSenderInterface => new NullSmsSender());

        $app->singleton(NotificationRepository::class, function () use ($app): NotificationRepository {
            $config = $app->config()->get('notifications', []);
            if (!is_array($config)) {
                $config = [];
            }
            $table = (string) ($config['table'] ?? 'notifications');
            $connections = $app->make(ConnectionManager::class);
            return new NotificationRepository($connections, $table);
        });

        $app->singleton(NotificationManager::class, function () use ($app): NotificationManager {
            $repo = $app->make(NotificationRepository::class);
            $mailer = $app->make(MailerInterface::class);
            $sms = $app->make(SmsSenderInterface::class);
            return new NotificationManager($repo, $app->config(), $mailer, $sms);
        });
    }

    public function boot(Container $app): void
    {
        $config = $app->config()->get('notifications', []);
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

        $table = (string) ($config['table'] ?? 'notifications');
        $connections = $app->make(ConnectionManager::class);
        NotificationsSchema::ensure($connections->connection(), $table);
    }
}


