<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Runtime;

final class Profiler
{
    private static ?self $current = null;

    private float $startedAt;
    private float $dbTimeMs = 0.0;
    private int $dbQueries = 0;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    private function __construct(float $startedAt)
    {
        $this->startedAt = $startedAt;
    }

    public static function start(?float $startedAt = null): self
    {
        $start = $startedAt ?? microtime(true);
        self::$current = new self($start);
        return self::$current;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function stop(): void
    {
        self::$current = null;
    }

    public static function recordDbTime(float $ms): void
    {
        $current = self::$current;
        if (!$current instanceof self) {
            return;
        }

        $current->dbTimeMs += $ms;
        $current->dbQueries++;
    }

    public static function recordCacheHit(): void
    {
        $current = self::$current;
        if (!$current instanceof self) {
            return;
        }

        $current->cacheHits++;
    }

    public static function recordCacheMiss(): void
    {
        $current = self::$current;
        if (!$current instanceof self) {
            return;
        }

        $current->cacheMisses++;
    }

    public function stats(): array
    {
        $elapsedMs = (microtime(true) - $this->startedAt) * 1000;

        return [
            'request_ms' => round($elapsedMs, 2),
            'db_ms' => round($this->dbTimeMs, 2),
            'db_queries' => $this->dbQueries,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
        ];
    }
}
