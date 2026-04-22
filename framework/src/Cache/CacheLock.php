<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

final class CacheLock
{
    private function __construct(
        private string $path,
        private string $type,
        private ?\Redis $redis = null,
        private ?string $token = null
    ) {
    }

    public static function acquireFile(string $basePath, string $key, int $ttlSeconds = 10): ?self
    {
        $lockPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.lock.' . sha1($key);
        $now = time();

        if (is_file($lockPath)) {
            $expiresAt = (int) @file_get_contents($lockPath);
            if ($expiresAt > $now) {
                return null;
            }
            @unlink($lockPath);
        }

        $payload = (string) ($now + max(1, $ttlSeconds));
        $created = @file_put_contents($lockPath, $payload, LOCK_EX);
        if ($created === false) {
            return null;
        }

        return new self($lockPath, 'file');
    }

    /**
     * @param array<string, int> $locks
     */
    public static function acquireMemory(string $key, array &$locks, int $ttlSeconds = 10): ?self
    {
        $now = time();
        $expiresAt = $locks[$key] ?? 0;
        if ($expiresAt > $now) {
            return null;
        }

        $locks[$key] = $now + max(1, $ttlSeconds);
        return new self($key, 'memory');
    }

    public static function acquireRedis(\Redis $redis, string $lockKey, int $ttlSeconds = 10): ?self
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $token = str_replace('.', '', uniqid('lock', true));
        }
        $ttlSeconds = max(1, $ttlSeconds);

        $acquired = $redis->set($lockKey, $token, ['nx', 'ex' => $ttlSeconds]);
        if ($acquired !== true) {
            return null;
        }

        return new self($lockKey, 'redis', $redis, $token);
    }

    /**
     * @param array<string, int> $locks
     */
    public function release(array &$locks = []): void
    {
        if ($this->type === 'file') {
            if (is_file($this->path)) {
                @unlink($this->path);
            }
            return;
        }

        if ($this->type === 'redis' && $this->redis instanceof \Redis) {
            $script = 'if redis.call("get", KEYS[1]) == ARGV[1] then return redis.call("del", KEYS[1]) else return 0 end';
            try {
                $this->redis->eval($script, [$this->path, (string) $this->token], 1);
            } catch (\Throwable) {
                // ignore
            }
            return;
        }

        unset($locks[$this->path]);
    }
}
