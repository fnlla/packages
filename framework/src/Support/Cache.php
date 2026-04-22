<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Cache\CacheStoreInterface;

final class Cache
{
    public function __construct(private CacheStoreInterface $store)
    {
    }

    public function store(): CacheStoreInterface
    {
        return $this->store;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->store->get($key);
        if ($value === null) {
            return is_callable($default) ? $default() : $default;
        }
        return $value;
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $this->store->put($key, $value, $ttlSeconds);
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $value = $this->store->get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        $this->store->put($key, $value, $ttlSeconds);
        return $value;
    }

    public function forget(string $key): void
    {
        $this->store->forget($key);
    }

    public function clear(): void
    {
        $this->store->clear();
    }
}





