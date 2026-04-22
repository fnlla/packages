<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Cache\CacheStoreInterface;
use Fnlla\Support\Psr\Cache\CacheItemInterface;
use Fnlla\Support\Psr\Cache\CacheItemPoolInterface;

final class CacheItemPool implements CacheItemPoolInterface
{
    private array $deferred = [];

    public function __construct(private CacheStoreInterface $store, private string $prefix = 'psr6:')
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        $item = new CacheItem($key);
        $value = $this->store->get($this->prefix . $key);
        if (is_array($value) && array_key_exists('__psr6', $value)) {
            $item->set($value['value'] ?? null);
            $item->markHit(true);
        }
        return $item;
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem((string) $key);
        }
        return $items;
    }

    public function hasItem(string $key): bool
    {
        $value = $this->store->get($this->prefix . $key);
        return is_array($value) && array_key_exists('__psr6', $value);
    }

    public function clear(): bool
    {
        $this->store->clear();
        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->store->forget($this->prefix . $key);
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem((string) $key);
        }
        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $ttl = 0;
        $expiresAt = $item->expirationTimestamp();
        if ($expiresAt > 0) {
            $ttl = max(0, $expiresAt - time());
        }
        $this->store->put($this->prefix . $item->getKey(), [
            '__psr6' => true,
            'value' => $item->get(),
        ], $ttl);
        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferred[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        foreach ($this->deferred as $item) {
            $this->save($item);
        }
        $this->deferred = [];
        return true;
    }
}






