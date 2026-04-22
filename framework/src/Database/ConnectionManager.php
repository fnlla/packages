<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

use PDO;
use RuntimeException;
use Fnlla\Support\Env;

final class ConnectionManager
{
    private ?PDO $connection = null;

    public function __construct(private array $config = [])
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $resolved = $this->resolveConfig($this->config);

        $this->connection = new PDO(
            $resolved['dsn'],
            $resolved['username'],
            $resolved['password'],
            $resolved['options']
        );

        if (($resolved['driver'] ?? '') === 'sqlite') {
            $timeout = (int) ($resolved['busy_timeout'] ?? 5000);
            if ($timeout > 0) {
                $this->connection->exec('PRAGMA busy_timeout = ' . $timeout);
            }
        }

        return $this->connection;
    }

    private function resolveConfig(array $config): array
    {
        $config = $this->extractDefaultConnection($config);

        $driver = strtolower((string) ($config['driver']
            ?? $config['connection']
            ?? $this->env('DB_CONNECTION', 'mysql')));

        if ($driver === '') {
            throw new RuntimeException('Database driver is not configured.');
        }

        $options = $this->buildOptions($config['options'] ?? null);

        if ($driver === 'sqlite') {
            $path = (string) ($config['path']
                ?? $config['database']
                ?? $this->env('DB_PATH', $this->env('DB_DATABASE', ':memory:')));

            $path = $path === '' ? ':memory:' : $path;

            return [
                'driver' => 'sqlite',
                'dsn' => 'sqlite:' . $path,
                'username' => null,
                'password' => null,
                'options' => $options,
                'busy_timeout' => (int) ($config['busy_timeout'] ?? $this->env('DB_BUSY_TIMEOUT', 5000)),
            ];
        }

        $host = (string) ($config['host'] ?? $this->env('DB_HOST', '127.0.0.1'));
        $port = (string) ($config['port'] ?? $this->env('DB_PORT', $driver === 'pgsql' ? '5432' : '3306'));
        $database = (string) ($config['database'] ?? $this->env('DB_DATABASE', ''));
        $username = (string) ($config['username'] ?? $this->env('DB_USERNAME', ''));
        $password = (string) ($config['password'] ?? $this->env('DB_PASSWORD', ''));

        if ($database === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
            $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $database;
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            $charset = (string) ($config['charset'] ?? $this->env('DB_CHARSET', 'utf8mb4'));
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=' . $charset;
        } else {
            throw new RuntimeException('Unsupported database driver: ' . $driver);
        }

        return [
            'driver' => $driver,
            'dsn' => $dsn,
            'username' => $username,
            'password' => $password,
            'options' => $options,
        ];
    }

    private function extractDefaultConnection(array $config): array
    {
        $default = $config['default'] ?? null;
        $connections = $config['connections'] ?? null;

        if (is_string($default) && is_array($connections) && isset($connections[$default]) && is_array($connections[$default])) {
            return $connections[$default];
        }

        return $config;
    }

    private function buildOptions(mixed $options): array
    {
        $resolved = is_array($options) ? $options : [];

        $resolved[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        $resolved[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
        $resolved[PDO::ATTR_EMULATE_PREPARES] = false;

        return $resolved;
    }

    private function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}
