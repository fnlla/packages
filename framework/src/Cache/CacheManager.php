<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

use RuntimeException;
use Fnlla\Support\Env;
use Fnlla\Runtime\Profiler;

final class CacheManager
{
    private ArrayCacheStore|FileCacheStore|RedisCacheStore|null $store = null;
    /**
     * @var array<string, int>
     */
    private static array $memoryLocks = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function store(): ArrayCacheStore|FileCacheStore|RedisCacheStore
    {
        if ($this->store !== null) {
            return $this->store;
        }

        $driver = strtolower((string) ($this->config['driver'] ?? 'file'));
        $ttl = $this->normaliseTtl($this->config['ttl'] ?? null);

        if ($driver === 'array') {
            $this->store = new ArrayCacheStore($ttl);
            return $this->store;
        }

        if ($driver === 'redis') {
            $redisConfig = $this->config['redis'] ?? [];
            if (!is_array($redisConfig)) {
                $redisConfig = [];
            }
            $this->store = new RedisCacheStore($redisConfig, $ttl);
            return $this->store;
        }

        $path = (string) ($this->config['path'] ?? '');
        if ($path === '') {
            $path = $this->defaultCachePath();
        }

        $this->store = new FileCacheStore($path, $ttl);
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $store = $this->store();
        $payload = null;

        if (method_exists($store, 'getPayload')) {
            $payload = $store->getPayload($key);
            if ($payload === null) {
                Profiler::recordCacheMiss();
                return $default;
            }
            Profiler::recordCacheHit();
            return $payload['value'];
        }

        $value = $store->get($key, $default);
        if ($value === $default) {
            Profiler::recordCacheMiss();
        } else {
            Profiler::recordCacheHit();
        }
        return $value;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->store()->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->store()->delete($key);
    }

    public function clear(): bool
    {
        return $this->store()->clear();
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = new \stdClass();
        $cached = $this->get($key, $sentinel);
        if ($cached !== $sentinel) {
            return $cached;
        }

        $lock = $this->acquireLock($key, min(10, max(1, (int) ($ttl / 2))));
        if ($lock instanceof CacheLock) {
            try {
                $value = $callback();
                $this->set($key, $value, $ttl);
                return $value;
            } finally {
                $lock->release(self::$memoryLocks);
            }
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            usleep(50000);
            $cached = $this->get($key, $sentinel);
            if ($cached !== $sentinel) {
                return $cached;
            }
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * @param array<int, string> $tags
     */
    public function tags(array $tags): TaggedCache
    {
        return new TaggedCache($this, $tags);
    }

    public function invalidateTag(string $tag): bool
    {
        $version = $this->getTagVersion($tag);
        return $this->store()->set($this->tagKey($tag), $version + 1);
    }

    /**
     * @param array<int, string> $tags
     */
    public function taggedKey(array $tags, string $key): string
    {
        $tags = array_values(array_filter(array_map('strval', $tags), fn ($item) => $item !== ''));
        sort($tags);

        $parts = [];
        foreach ($tags as $tag) {
            $parts[] = $tag . ':' . $this->getTagVersion($tag);
        }

        $namespace = sha1(implode('|', $parts));
        return 'tagged:' . $namespace . ':' . $key;
    }

    private function getTagVersion(string $tag): int
    {
        $key = $this->tagKey($tag);
        $current = (int) $this->store()->get($key, 1);
        if ($current < 1) {
            $current = 1;
        }
        $this->store()->set($key, $current);
        return $current;
    }

    private function tagKey(string $tag): string
    {
        return 'tag:' . sha1($tag);
    }

    private function normaliseTtl(mixed $ttl): ?int
    {
        if ($ttl === null || $ttl === '') {
            return null;
        }

        if (is_numeric($ttl)) {
            return (int) $ttl;
        }

        return null;
    }

    private function defaultCachePath(): string
    {
        $appRoot = $this->env('APP_ROOT');
        if ($appRoot !== '') {
            return rtrim($appRoot, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        }

        return getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
    }

    private function env(string $key, mixed $default = ''): string
    {
        return (string) Env::get($key, $default);
    }

    private function acquireLock(string $key, int $ttlSeconds): ?CacheLock
    {
        $store = $this->store();
        if ($store instanceof FileCacheStore) {
            return CacheLock::acquireFile($store->basePath(), $key, $ttlSeconds);
        }

        if ($store instanceof RedisCacheStore) {
            return CacheLock::acquireRedis($store->connection(), $store->lockPrefix() . sha1($key), $ttlSeconds);
        }

        return CacheLock::acquireMemory($key, self::$memoryLocks, $ttlSeconds);
    }
}
