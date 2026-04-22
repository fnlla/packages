<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Runtime;

final class RequestContext
{
    private static ?self $current = null;

    private string $requestId;
    private float $startedAt;
    private ?string $locale;
    private string $cspNonce;
    private string $traceId;
    private string $spanId;
    private bool $includeRequestIdHeader = true;
    private bool $includeTraceIdHeader = true;
    private bool $includeSpanIdHeader = true;

    public function __construct(
        private ResetManager $resetManager,
        ?string $requestId = null,
        ?float $startedAt = null,
        ?string $locale = null,
        ?string $cspNonce = null,
        ?string $traceId = null,
        ?string $spanId = null
    ) {
        $requestId = trim((string) $requestId);
        $this->requestId = $requestId !== '' ? $requestId : self::generateRequestId();
        $this->startedAt = $startedAt ?? microtime(true);
        $locale = trim((string) $locale);
        $this->locale = $locale !== '' ? $locale : null;
        $cspNonce = trim((string) $cspNonce);
        $this->cspNonce = $cspNonce !== '' ? $cspNonce : self::generateCspNonce();
        $traceId = trim((string) $traceId);
        $this->traceId = $traceId !== '' ? $traceId : self::generateTraceId();
        $spanId = trim((string) $spanId);
        $this->spanId = $spanId !== '' ? $spanId : self::generateSpanId();
    }

    public function begin(): void
    {
        self::$current = $this;
    }

    public function end(): void
    {
        if (self::$current === $this) {
            self::$current = null;
        }
        $this->resetManager->reset();
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function startedAt(): float
    {
        return $this->startedAt;
    }

    public function locale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $locale = trim((string) $locale);
        $this->locale = $locale !== '' ? $locale : null;
    }

    public function cspNonce(): string
    {
        return $this->cspNonce;
    }

    public function setCspNonce(?string $nonce): void
    {
        $nonce = trim((string) $nonce);
        $this->cspNonce = $nonce !== '' ? $nonce : $this->cspNonce;
    }

    public function traceId(): string
    {
        return $this->traceId;
    }

    public function spanId(): string
    {
        return $this->spanId;
    }

    public function setHeaderFlags(bool $requestId, bool $traceId, bool $spanId): void
    {
        $this->includeRequestIdHeader = $requestId;
        $this->includeTraceIdHeader = $traceId;
        $this->includeSpanIdHeader = $spanId;
    }

    public function includeRequestIdHeader(): bool
    {
        return $this->includeRequestIdHeader;
    }

    public function includeTraceIdHeader(): bool
    {
        return $this->includeTraceIdHeader;
    }

    public function includeSpanIdHeader(): bool
    {
        return $this->includeSpanIdHeader;
    }

    private static function generateRequestId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return str_replace('.', '', uniqid('', true));
        }
    }

    private static function generateCspNonce(): string
    {
        try {
            return base64_encode(random_bytes(16));
        } catch (\Throwable $e) {
            return str_replace('.', '', uniqid('', true));
        }
    }

    private static function generateTraceId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return str_replace('.', '', uniqid('', true));
        }
    }

    private static function generateSpanId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            return substr(str_replace('.', '', uniqid('', true)), 0, 16);
        }
    }
}


