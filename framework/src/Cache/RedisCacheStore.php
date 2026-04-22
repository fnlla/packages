<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

use Fnlla\Support\RedisConnector;

final class RedisCacheStore
{
    private ?\Redis $redis = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [], private ?int $defaultTtl = null)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->getPayload($key);
        if ($payload === null) {
            return $default;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $ttl = $this->resolveTtl($ttl);
        $payload = base64_encode(serialize($value));
        $redis = $this->connection();
        $key = $this->prefixKey($key);

        if ($ttl !== null && $ttl > 0) {
            return $redis->setex($key, $ttl, $payload) === true;
        }

        return $redis->set($key, $payload) === true;
    }

    public function delete(string $key): bool
    {
        $redis = $this->connection();
        /** @psalm-suppress RedundantCast */
        return (int) $redis->del($this->prefixKey($key)) > 0;
    }

    public function clear(): bool
    {
        $redis = $this->connection();
        $prefix = $this->prefix();

        if ($prefix === '') {
            return $redis->flushDB() === true;
        }

        $cursor = null;
        do {
            $result = $redis->scan($cursor, $prefix . '*', 200);
            if (is_array($result) && $result !== []) {
                $redis->del(...$result);
            }
        } while ($cursor !== 0 && $cursor !== null);

        return true;
    }

    public function has(string $key): bool
    {
        $redis = $this->connection();
        /** @psalm-suppress RedundantCast */
        return (int) $redis->exists($this->prefixKey($key)) > 0;
    }

    /**
     * @return array{expires_at: int|null, value: mixed}|null
     */
    public function getPayload(string $key): ?array
    {
        $redis = $this->connection();
        $raw = $redis->get($this->prefixKey($key));
        if ($raw === false) {
            return null;
        }

        $value = @unserialize(base64_decode($raw, true) ?: '', ['allowed_classes' => false]);
        return [
            'expires_at' => null,
            'value' => $value,
        ];
    }

    public function connection(): \Redis
    {
        if ($this->redis instanceof \Redis) {
            return $this->redis;
        }

        $this->redis = RedisConnector::connect($this->config);
        return $this->redis;
    }

    public function lockPrefix(): string
    {
        $prefix = (string) ($this->config['lock_prefix'] ?? '');
        if ($prefix !== '') {
            return $prefix;
        }

        return $this->prefix() . 'lock:';
    }

    private function resolveTtl(?int $ttl): ?int
    {
        $ttl = $ttl ?? $this->defaultTtl;
        if ($ttl === null || $ttl <= 0) {
            return null;
        }

        return $ttl;
    }

    private function prefix(): string
    {
        return (string) ($this->config['prefix'] ?? '');
    }

    private function prefixKey(string $key): string
    {
        $prefix = $this->prefix();
        if ($prefix === '') {
            return $key;
        }

        return $prefix . $key;
    }
}
