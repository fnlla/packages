<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use RuntimeException;

final class ProviderValidator
{
    /**
     * @return string[] warnings or errors
     */
    public function validate(ProviderManifest $m, string $appRoot): array
    {
        $appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
        $issues = [];

        if (in_array(ProviderCapability::ROUTES, $m->capabilities, true)) {
            $routes = $this->resolvePath($m->resources['routes'] ?? null, $appRoot);
            if ($routes === null || !is_file($routes)) {
                $issues[] = 'Routes capability requires existing file: ' . ($routes ?? '(missing)');
            }
        }

        if (in_array(ProviderCapability::VIEWS, $m->capabilities, true)) {
            $views = $this->resolvePath($m->resources['views'] ?? null, $appRoot);
            if ($views === null || !is_dir($views)) {
                $issues[] = 'Views capability requires existing directory: ' . ($views ?? '(missing)');
            }
        }

        if (in_array(ProviderCapability::CONFIG, $m->capabilities, true)) {
            $config = $this->resolvePath($m->resources['config'] ?? null, $appRoot);
            if ($config === null || (!is_file($config) && !is_dir($config))) {
                $issues[] = 'Config capability requires existing file or directory: ' . ($config ?? '(missing)');
            }
        }

        if ($this->isProdEnv() && $issues !== []) {
            throw new RuntimeException('Provider manifest validation failed: ' . implode('; ', $issues));
        }

        return $issues;
    }

    private function resolvePath(mixed $value, string $appRoot): ?string
    {
        if (is_string($value) && $value !== '') {
            return $this->normalizePath($value, $appRoot);
        }

        if (is_array($value)) {
            if (isset($value['file']) && is_string($value['file']) && $value['file'] !== '') {
                return $this->normalizePath($value['file'], $appRoot);
            }
            if (isset($value['dir']) && is_string($value['dir']) && $value['dir'] !== '') {
                return $this->normalizePath($value['dir'], $appRoot);
            }
            foreach ($value as $item) {
                if (is_string($item) && $item !== '') {
                    return $this->normalizePath($item, $appRoot);
                }
            }
        }

        return null;
    }

    private function normalizePath(string $path, string $appRoot): string
    {
        if ($this->isAbsolutePath($path) || $appRoot === '') {
            return $path;
        }

        return $appRoot . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    private function isProdEnv(): bool
    {
        $env = strtolower((string) getenv('APP_ENV'));
        if ($env === '') {
            $env = 'prod';
        }

        return in_array($env, ['prod', 'production'], true);
    }
}
