<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\RateLimit;

final class RateLimitState
{
    public function __construct(
        private bool $allowed,
        private int $remaining,
        private int $retryAfter,
        private int $resetAt
    ) {
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function remaining(): int
    {
        return $this->remaining;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    public function resetAt(): int
    {
        return $this->resetAt;
    }
}
