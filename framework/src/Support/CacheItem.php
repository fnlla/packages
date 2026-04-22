<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Fnlla\Support\Psr\Cache\CacheItemInterface;

final class CacheItem implements CacheItemInterface
{
    private mixed $value = null;
    private bool $hit = false;
    private ?DateTimeInterface $expiresAt = null;

    public function __construct(private string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): self
    {
        $this->expiresAt = $expiration;
        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): self
    {
        if ($time === null) {
            $this->expiresAt = null;
            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expiresAt = (new DateTimeImmutable())->add($time);
            return $this;
        }

        $this->expiresAt = (new DateTimeImmutable())->modify('+' . $time . ' seconds');
        return $this;
    }

    public function markHit(bool $hit): void
    {
        $this->hit = $hit;
    }

    public function expirationTimestamp(): int
    {
        if ($this->expiresAt === null) {
            return 0;
        }
        return $this->expiresAt->getTimestamp();
    }
}






