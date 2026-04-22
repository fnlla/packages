<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use DateTimeImmutable;
use Fnlla\Contracts\Cache\CacheStoreInterface;
use Fnlla\Support\Psr\SimpleCache\CacheInterface;

final class SimpleCacheAdapter implements CacheInterface
{
    public function __construct(private CacheStoreInterface $store, private string $prefix = '')
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($this->prefix . $key);
        return $value === null ? $default : $value;
    }

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $seconds = $this->ttlToSeconds($ttl);
        $this->store->put($this->prefix . $key, $value, $seconds);
        return true;
    }

    public function delete(string $key): bool
    {
        $this->store->forget($this->prefix . $key);
        return true;
    }

    public function clear(): bool
    {
        $this->store->clear();
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get((string) $key, $default);
        }
        return $results;
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->store->get($this->prefix . $key) !== null;
    }

    private function ttlToSeconds(null|int|\DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }
        if (is_int($ttl)) {
            return $ttl;
        }
        $now = new DateTimeImmutable();
        $future = $now->add($ttl);
        return max(0, $future->getTimestamp() - $now->getTimestamp());
    }
}






