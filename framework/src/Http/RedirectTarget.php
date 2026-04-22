<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

/**
 * Safe redirect target resolver.
 *
 * @api
 */
final class RedirectTarget
{
    public static function fromReferer(Request $request, string $fallback = '/'): string
    {
        $referer = trim((string) $request->getHeaderLine('Referer'));
        return self::sanitize($referer, $fallback);
    }

    private static function sanitize(string $referer, string $fallback): string
    {
        if ($referer === '') {
            return $fallback;
        }

        if (str_starts_with($referer, '//')) {
            return $fallback;
        }

        if (str_contains($referer, '://')) {
            return $fallback;
        }

        if (!str_starts_with($referer, '/')) {
            return $fallback;
        }

        return $referer;
    }
}
