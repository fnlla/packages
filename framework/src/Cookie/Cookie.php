<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cookie;

use InvalidArgumentException;

final class Cookie
{
    public function __construct(
        public string $name,
        public string $value,
        public int $lifetime = 0,
        public string $path = '/',
        public string $domain = '',
        public bool $secure = false,
        public bool $httpOnly = true,
        public string $sameSite = 'Lax'
    ) {
        $this->sameSite = self::normalizeSameSite($this->sameSite);
        if ($this->sameSite === 'None') {
            $this->secure = true;
        }
    }

    public function toHeader(): string
    {
        $parts = [rawurlencode($this->name) . '=' . rawurlencode($this->value)];
        if ($this->lifetime > 0) {
            $parts[] = 'Max-Age=' . $this->lifetime;
        }
        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }
        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->sameSite !== '') {
            $parts[] = 'SameSite=' . $this->sameSite;
        }
        return implode('; ', $parts);
    }

    private static function normalizeSameSite(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $normalized = ucfirst(strtolower($value));
        $allowed = ['Lax', 'Strict', 'None'];
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException('Invalid SameSite value: ' . $value);
        }

        return $normalized;
    }
}
