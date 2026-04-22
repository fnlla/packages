<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

/**
 * Configuration repository loaded from `config/<group>/*.php` (with optional subdirectories).
 *
 * @api
 */
final class ConfigRepository
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function fromRoot(string $root): self
    {
        $cachePath = self::resolveCachePath($root);
        if ($cachePath !== null && is_file($cachePath)) {
            $cached = require $cachePath;
            if (self::isValidCache($cached)) {
                return new self($cached);
            }
        }

        $configPath = getenv('APP_CONFIG_PATH');
        if (is_string($configPath) && $configPath !== '' && is_file($configPath)) {
            $config = require $configPath;
            return new self(is_array($config) ? $config : []);
        }

        $configDir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
        return self::fromDirectory($configDir);
    }

    public static function resolveAppRoot(): string
    {
        if (defined('APP_ROOT') && is_string(APP_ROOT) && APP_ROOT !== '') {
            return APP_ROOT;
        }

        $envRoot = getenv('APP_ROOT');
        if (is_string($envRoot) && $envRoot !== '') {
            return $envRoot;
        }

        $configPath = getenv('APP_CONFIG_PATH');
        if (is_string($configPath) && $configPath !== '') {
            return dirname($configPath);
        }

        $candidates = [
            dirname(__DIR__, 4), // app repo when framework lives in /framework/FnllaPHP/src/Core
            dirname(__DIR__, 2), // framework repo root if used standalone
            dirname(__DIR__, 3),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate . '/config/app.php')) {
                return $candidate;
            }
        }

        return dirname(__DIR__, 2);
    }

    public static function fromDirectory(string $configDir): self
    {
        $config = [];
        $appConfig = [];

        $appFile = rtrim($configDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app.php';
        if (is_file($appFile)) {
            $appConfig = require $appFile;
            if (is_array($appConfig)) {
                $config = $appConfig;
            }
        }

        if (is_dir($configDir)) {
            $files = self::listConfigFiles($configDir);
            foreach ($files as $file) {
                $relative = substr($file, strlen(rtrim($configDir, DIRECTORY_SEPARATOR)) + 1);
                if ($relative === '') {
                    continue;
                }
                $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);
                if ($relative === 'app.php' || $relative === 'routes.php') {
                    continue;
                }
                $key = self::normalizeConfigKey($relative);
                if ($key === 'app' || $key === 'routes') {
                    continue;
                }
                $data = require $file;
                if (is_array($data)) {
                    $config[$key] = $data;
                }
            }
        }

        if ($appConfig !== []) {
            $config['app'] = $config['app'] ?? $appConfig;
        }

        return new self($config);
    }

    public function all(): array
    {
        return $this->items;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $default;
        }

        if (!str_contains($key, '.')) {
            return $this->items[$key] ?? $default;
        }

        $current = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    public function set(string $key, mixed $value): void
    {
        if ($key === '') {
            return;
        }

        if (!str_contains($key, '.')) {
            $this->items[$key] = $value;
            return;
        }

        $segments = explode('.', $key);
        $current = &$this->items;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                return;
            }
            if ($index === $lastIndex) {
                $current[$segment] = $value;
                return;
            }
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
    }

    public function forget(string $key): void
    {
        if ($key === '') {
            return;
        }

        if (!str_contains($key, '.')) {
            unset($this->items[$key]);
            return;
        }

        $segments = explode('.', $key);
        $last = array_pop($segments);
        if ($last === null || $last === '') {
            return;
        }

        $current = &$this->items;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return;
            }
            $current = &$current[$segment];
        }

        if (is_array($current) && array_key_exists($last, $current)) {
            unset($current[$last]);
        }
    }

    private static function resolveCachePath(string $root): ?string
    {
        $setting = getenv('APP_CONFIG_CACHE');
        $default = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'config.php';

        if ($setting === false) {
            return $default;
        }

        $setting = trim((string) $setting);
        if ($setting === '' || $setting === '1' || strtolower($setting) === 'true') {
            return $default;
        }

        if (in_array(strtolower($setting), ['0', 'false', 'off'], true)) {
            return null;
        }

        return $setting;
    }

    /**
     * @return string[]
     */
    private static function listConfigFiles(string $configDir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($configDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }
            $files[] = $fileInfo->getPathname();
        }

        usort($files, static function (string $left, string $right) use ($configDir): int {
            $base = rtrim($configDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $leftRelative = str_replace(['\\', '/'], '/', substr($left, strlen($base)));
            $rightRelative = str_replace(['\\', '/'], '/', substr($right, strlen($base)));
            $leftDepth = substr_count($leftRelative, '/');
            $rightDepth = substr_count($rightRelative, '/');

            if ($leftDepth === $rightDepth) {
                return $leftRelative <=> $rightRelative;
            }

            return $rightDepth <=> $leftDepth;
        });

        return $files;
    }

    private static function normalizeConfigKey(string $relativePath): string
    {
        $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
        $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            return '';
        }

        $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
        $file = array_pop($segments);
        if ($file === null || $file === '') {
            return '';
        }

        $name = pathinfo($file, PATHINFO_FILENAME);
        if ($segments === []) {
            return $name;
        }

        $lastSegment = $segments[count($segments) - 1] ?? '';
        $prefixUnderscore = $lastSegment !== '' ? $lastSegment . '_' : '';
        $prefixDash = $lastSegment !== '' ? $lastSegment . '-' : '';
        if ($prefixUnderscore !== '' && str_starts_with($name, $prefixUnderscore)) {
            return $name;
        }
        if ($prefixDash !== '' && str_starts_with($name, $prefixDash)) {
            return $name;
        }
        if ($name === 'index' || $name === $lastSegment) {
            return implode('_', $segments);
        }

        $segments[] = $name;
        return implode('_', $segments);
    }

    private static function isValidCache(mixed $cached): bool
    {
        if (!is_array($cached)) {
            return false;
        }
        foreach ($cached as $key => $_) {
            if (!is_string($key)) {
                return false;
            }
        }
        return true;
    }
}
