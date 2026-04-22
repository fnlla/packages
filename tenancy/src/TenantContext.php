<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Tenancy;

final class TenantContext
{
    private static ?string $id = null;
    private static array $meta = [];

    public static function setId(?string $id, array $meta = []): void
    {
        $id = is_string($id) ? trim($id) : '';
        self::$id = $id !== '' ? $id : null;
        self::$meta = $meta;
    }

    public static function id(): ?string
    {
        return self::$id;
    }

    public static function isSet(): bool
    {
        return self::$id !== null;
    }

    public static function meta(string $key, mixed $default = null): mixed
    {
        return self::$meta[$key] ?? $default;
    }

    public static function clear(): void
    {
        self::$id = null;
        self::$meta = [];
    }
}
