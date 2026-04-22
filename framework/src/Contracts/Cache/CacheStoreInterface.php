<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Cache;

/**
 * @api
 */
interface CacheStoreInterface
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value, int $ttlSeconds = 0): void;

    public function forget(string $key): void;

    public function clear(): void;
}




