<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications;

use PDO;

final class NotificationsSchema
{
    public static function ensure(PDO $pdo, string $table = 'notifications'): void
    {
        $table = $table !== '' ? $table : 'notifications';
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id SERIAL PRIMARY KEY, channel VARCHAR(32) NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NULL, body TEXT NOT NULL, status VARCHAR(32) NOT NULL, error TEXT NULL, metadata JSON NULL, created_at TIMESTAMP NOT NULL, sent_at TIMESTAMP NULL)');
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, channel TEXT NOT NULL, recipient TEXT NOT NULL, subject TEXT NULL, body TEXT NOT NULL, status TEXT NOT NULL, error TEXT NULL, metadata TEXT NULL, created_at TEXT NOT NULL, sent_at TEXT NULL)');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, channel VARCHAR(32) NOT NULL, recipient VARCHAR(255) NOT NULL, subject VARCHAR(255) NULL, body TEXT NOT NULL, status VARCHAR(32) NOT NULL, error TEXT NULL, metadata JSON NULL, created_at DATETIME NOT NULL, sent_at DATETIME NULL)');
    }
}


