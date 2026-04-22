<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Settings;

use PDO;

final class SettingsSchema
{
    public static function ensure(PDO $pdo, string $table = 'settings'): void
    {
        $table = $table !== '' ? $table : 'settings';
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (setting_key VARCHAR(120) PRIMARY KEY, setting_value TEXT NULL, updated_at TIMESTAMP NOT NULL)');
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (setting_key TEXT PRIMARY KEY, setting_value TEXT NULL, updated_at TEXT NOT NULL)');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (setting_key VARCHAR(120) NOT NULL PRIMARY KEY, setting_value TEXT NULL, updated_at DATETIME NOT NULL)');
    }
}
