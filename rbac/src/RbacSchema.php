<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Rbac;

use PDO;

final class RbacSchema
{
    public static function ensure(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS roles (id SERIAL PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permissions (id SERIAL PRIMARY KEY, name VARCHAR(150) UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permission_role (role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY (role_id, permission_id))');
            $pdo->exec('CREATE TABLE IF NOT EXISTS role_user (user_id VARCHAR(64) NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id))');
        } elseif ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permission_role (role_id INTEGER NOT NULL, permission_id INTEGER NOT NULL, PRIMARY KEY (role_id, permission_id))');
            $pdo->exec('CREATE TABLE IF NOT EXISTS role_user (user_id TEXT NOT NULL, role_id INTEGER NOT NULL, PRIMARY KEY (user_id, role_id))');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS roles (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permissions (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) UNIQUE NOT NULL)');
            $pdo->exec('CREATE TABLE IF NOT EXISTS permission_role (role_id INT NOT NULL, permission_id INT NOT NULL, PRIMARY KEY (role_id, permission_id))');
            $pdo->exec('CREATE TABLE IF NOT EXISTS role_user (user_id VARCHAR(64) NOT NULL, role_id INT NOT NULL, PRIMARY KEY (user_id, role_id))');
        }
    }
}
