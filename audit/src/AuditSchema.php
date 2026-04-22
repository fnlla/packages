<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

use PDO;

final class AuditSchema
{
    public static function ensure(PDO $pdo, string $table = 'audit_log'): void
    {
        $table = $table !== '' ? $table : 'audit_log';
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id SERIAL PRIMARY KEY, user_id INT NULL, action VARCHAR(64) NOT NULL, entity_type VARCHAR(64) NULL, entity_id INT NULL, ip_address VARCHAR(64) NULL, user_agent VARCHAR(255) NULL, metadata JSON NULL, created_at TIMESTAMP NOT NULL)');
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, action TEXT NOT NULL, entity_type TEXT NULL, entity_id INTEGER NULL, ip_address TEXT NULL, user_agent TEXT NULL, metadata TEXT NULL, created_at TEXT NOT NULL)');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, action VARCHAR(64) NOT NULL, entity_type VARCHAR(64) NULL, entity_id INT NULL, ip_address VARCHAR(64) NULL, user_agent VARCHAR(255) NULL, metadata JSON NULL, created_at DATETIME NOT NULL)');
    }
}




