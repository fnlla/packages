<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

final class ArrayCacheStore
{
    /**
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    private array $items = [];

    public function __construct(private ?int $defaultTtl = null)
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
        $expiresAt = $this->expiresAt($ttl);
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->items = [];
        return true;
    }

    public function has(string $key): bool
    {
        return $this->getPayload($key) !== null;
    }

    /**
     * @return array{value: mixed, expires_at: int|null}|null
     */
    public function getPayload(string $key): ?array
    {
        if (!array_key_exists($key, $this->items)) {
            return null;
        }

        $expiresAt = $this->items[$key]['expires_at'];
        if ($expiresAt !== null && $expiresAt <= time()) {
            unset($this->items[$key]);
            return null;
        }

        return $this->items[$key];
    }

    private function expiresAt(?int $ttl): ?int
    {
        $ttl = $ttl ?? $this->defaultTtl;
        if ($ttl === null || $ttl <= 0) {
            return null;
        }

        return time() + $ttl;
    }
}
