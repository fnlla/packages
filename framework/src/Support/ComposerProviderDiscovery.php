<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

final class ComposerProviderDiscovery
{
    public static function discover(string $vendorDir): array
    {
        $vendorDir = rtrim($vendorDir, DIRECTORY_SEPARATOR);
        if ($vendorDir === '') {
            return ['providers' => [], 'meta' => []];
        }

        $path = $vendorDir . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.json';
        if (!is_file($path)) {
            return ['providers' => [], 'meta' => []];
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            return ['providers' => [], 'meta' => []];
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return ['providers' => [], 'meta' => []];
        }

        $packages = [];
        if (isset($data['packages']) && is_array($data['packages'])) {
            $packages = $data['packages'];
        } elseif (array_is_list($data)) {
            $packages = $data;
        }

        if ($packages === []) {
            return ['providers' => [], 'meta' => []];
        }

        $providers = [];
        $meta = [];
        foreach ($packages as $package) {
            if (!is_array($package)) {
                continue;
            }
            $packageName = is_string($package['name'] ?? null) ? $package['name'] : null;
            $packageVersion = is_string($package['version'] ?? null) ? $package['version'] : null;
            $extra = $package['extra'] ?? null;
            if (!is_array($extra)) {
                continue;
            }
            $Fnlla = $extra['fnlla'] ?? $extra['Fnlla'] ?? null;
            if (!is_array($Fnlla)) {
                continue;
            }
            $list = $Fnlla['providers'] ?? null;
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $provider) {
                if (is_string($provider) && $provider !== '') {
                    $providers[$provider] = true;
                    if (!isset($meta[$provider])) {
                        $entry = ['source' => 'auto'];
                        if (is_string($packageName) && $packageName !== '') {
                            $entry['package'] = $packageName;
                        }
                        if (is_string($packageVersion) && $packageVersion !== '') {
                            $entry['version'] = $packageVersion;
                        }
                        $meta[$provider] = $entry;
                    }
                }
            }
        }

        return [
            'providers' => array_keys($providers),
            'meta' => $meta,
        ];
    }
}
