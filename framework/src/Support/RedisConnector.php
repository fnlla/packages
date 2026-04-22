<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support;

use RuntimeException;

final class RedisConnector
{
    public static function connect(array $config = []): \Redis
    {
        if (!class_exists(\Redis::class)) {
            throw new RuntimeException('Redis extension is not installed. Install ext-redis to use Redis drivers.');
        }

        $config = self::normalizeConfig($config);

        $redis = new \Redis();
        $connected = false;

        if ($config['persistent'] === true) {
            $persistentId = $config['persistent_id'] !== '' ? $config['persistent_id'] : null;
            $connected = $redis->pconnect($config['host'], $config['port'], $config['timeout'], $persistentId);
        } else {
            $connected = $redis->connect($config['host'], $config['port'], $config['timeout']);
        }

        if ($connected !== true) {
            throw new RuntimeException('Unable to connect to Redis.');
        }

        if ($config['read_timeout'] !== null) {
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, $config['read_timeout']);
        }

        if ($config['username'] !== '' || $config['password'] !== '') {
            $auth = $config['password'];
            if ($config['username'] !== '') {
                $auth = [$config['username'], $config['password']];
            }

            /** @psalm-suppress PossiblyInvalidArgument, PossiblyInvalidCast */
            if ($redis->auth($auth) !== true) {
                throw new RuntimeException('Redis authentication failed.');
            }
        }

        if ($config['database'] !== null) {
            $redis->select($config['database']);
        }

        return $redis;
    }

    private static function normalizeConfig(array $config): array
    {
        $config = self::mergeUrlConfig($config);

        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 6379);
        $timeout = (float) ($config['timeout'] ?? 1.5);
        $readTimeout = $config['read_timeout'] ?? null;
        $readTimeout = $readTimeout === null || $readTimeout === '' ? null : (float) $readTimeout;
        $persistent = $config['persistent'] ?? false;
        if (is_string($persistent)) {
            $persistent = in_array(strtolower($persistent), ['1', 'true', 'yes', 'on'], true);
        }
        $persistent = (bool) $persistent;

        $database = $config['database'] ?? null;
        if ($database === '' || $database === null) {
            $database = null;
        } else {
            $database = (int) $database;
        }

        $prefix = (string) ($config['prefix'] ?? '');
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $persistentId = (string) ($config['persistent_id'] ?? '');

        return [
            'host' => $host,
            'port' => $port,
            'timeout' => $timeout,
            'read_timeout' => $readTimeout,
            'persistent' => $persistent,
            'persistent_id' => $persistentId,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'prefix' => $prefix,
        ];
    }

    private static function mergeUrlConfig(array $config): array
    {
        $url = $config['url'] ?? '';
        if (!is_string($url) || trim($url) === '') {
            return $config;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $config;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $port = $parts['port'] ?? null;
        $user = (string) ($parts['user'] ?? '');
        $pass = (string) ($parts['pass'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        $db = null;

        if ($path !== '') {
            $trimmed = ltrim($path, '/');
            if ($trimmed !== '' && ctype_digit($trimmed)) {
                $db = (int) $trimmed;
            }
        }

        if ($scheme === 'rediss' && $host !== '' && !str_starts_with($host, 'tls://')) {
            $host = 'tls://' . $host;
        }

        if ($host !== '') {
            $config['host'] = $host;
        }
        if ($port !== null) {
            $config['port'] = (int) $port;
        }
        if ($user !== '') {
            $config['username'] = $user;
        }
        if ($pass !== '') {
            $config['password'] = $pass;
        }
        if ($db !== null) {
            $config['database'] = $db;
        }

        return $config;
    }
}
