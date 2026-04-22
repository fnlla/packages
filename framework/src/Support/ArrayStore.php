<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Cache\CacheStoreInterface;

class ArrayStore implements CacheStoreInterface
{
    private array $items = [];

    public function get(string $key): mixed
    {
        if (!isset($this->items[$key])) {
            return null;
        }

        $entry = $this->items[$key];
        if ($entry['expires_at'] !== 0 && $entry['expires_at'] < time()) {
            unset($this->items[$key]);
            return null;
        }

        return $entry['value'];
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $this->items[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }

    public function clear(): void
    {
        $this->items = [];
    }
}





