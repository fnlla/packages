<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use RuntimeException;

final class ProviderCache
{
    public static function write(string $path, array $providers): void
    {
        if ($path === '') {
            throw new RuntimeException('Provider cache path is empty.');
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create provider cache directory: ' . $dir);
        }

        $payload = self::normalizePayload($providers);
        $export = var_export($payload, true);
        $contents = "<?php\n\nreturn " . $export . ";\n";

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write provider cache file: ' . $path);
        }
    }

    private static function normalizePayload(array $providers): array
    {
        if (isset($providers['providers']) || isset($providers['meta'])) {
            $list = $providers['providers'] ?? [];
            $meta = $providers['meta'] ?? [];
            if (!is_array($list)) {
                $list = [];
            }
            if (!is_array($meta)) {
                $meta = [];
            }
            return [
                'providers' => array_values(array_filter($list, 'is_string')),
                'meta' => $meta,
            ];
        }

        return [
            'providers' => array_values(array_filter($providers, 'is_string')),
            'meta' => [],
        ];
    }
}
