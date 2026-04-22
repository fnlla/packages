<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\RateLimit;

use Fnlla\Cache\CacheManager;

final class RateLimiter
{
    public function __construct(private CacheManager $cache)
    {
    }

    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        return $this->consume($key, $maxAttempts, $decaySeconds)->allowed();
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $now = time();
        $data = $this->cache->get($key);
        if (!is_array($data) || !isset($data['count'], $data['reset_at'])) {
            return $maxAttempts;
        }

        $resetAt = (int) $data['reset_at'];
        if ($resetAt <= $now) {
            return $maxAttempts;
        }

        $count = (int) $data['count'];
        return max(0, $maxAttempts - $count);
    }

    public function consume(string $key, int $maxAttempts, int $decaySeconds): RateLimitState
    {
        $now = time();
        $decaySeconds = max(1, $decaySeconds);

        $data = $this->cache->get($key);
        if (!is_array($data) || !isset($data['count'], $data['reset_at'])) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $decaySeconds,
            ];
        }

        $resetAt = (int) $data['reset_at'];
        if ($resetAt <= $now) {
            $data = [
                'count' => 0,
                'reset_at' => $now + $decaySeconds,
            ];
            $resetAt = (int) $data['reset_at'];
        }

        $count = (int) $data['count'];
        $retryAfter = max(0, $resetAt - $now);

        if ($count >= $maxAttempts) {
            $this->cache->set($key, $data, $retryAfter);
            return new RateLimitState(false, 0, $retryAfter, $resetAt);
        }

        $count++;
        $data['count'] = $count;
        $this->cache->set($key, $data, $retryAfter);

        $remaining = max(0, $maxAttempts - $count);
        return new RateLimitState(true, $remaining, $retryAfter, $resetAt);
    }
}
