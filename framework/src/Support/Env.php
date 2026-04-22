<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support;

final class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('env')) {
            return self::parse(env($key, $default));
        }

        if (array_key_exists($key, $_ENV)) {
            return self::parse($_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER)) {
            return self::parse($_SERVER[$key]);
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return self::parse($value);
    }

    private static function parse(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));
        return match ($normalized) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
