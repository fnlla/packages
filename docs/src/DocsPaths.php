<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

use Fnlla\Core\ConfigRepository;

final class DocsPaths
{
    private string $root;
    private array $config;

    public function __construct(private ConfigRepository $appConfig, ?string $root = null)
    {
        $root = $root ?? ConfigRepository::resolveAppRoot();
        $this->root = rtrim($root, '/\\');
        $config = $this->appConfig->get('docs', []);
        $this->config = is_array($config) ? $config : [];
    }

    public function root(): string
    {
        return $this->root;
    }

    public function manual(): string
    {
        return $this->resolvePath($this->pathFromConfig('manual', $this->root . '/storage/docs/manual'));
    }

    public function generated(): string
    {
        return $this->resolvePath($this->pathFromConfig('generated', $this->root . '/storage/docs/generated'));
    }

    public function published(): string
    {
        return $this->resolvePath($this->pathFromConfig('published', $this->root . '/resources/docs'));
    }

    public function all(): array
    {
        $paths = $this->config['paths'] ?? [];
        if (is_array($paths) && array_is_list($paths)) {
            $resolved = [];
            foreach ($paths as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $resolved[] = $this->resolvePath($path);
            }
            return $this->unique($resolved);
        }
        $resolved = [
            $this->manual(),
            $this->generated(),
            $this->published(),
        ];

        $extra = $this->config['paths_extra'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $resolved[] = $this->resolvePath($path);
            }
        }

        return $this->unique($resolved);
    }

    public function manualFile(string $slug): string
    {
        return $this->filePath($this->manual(), $slug);
    }

    public function generatedFile(string $slug): string
    {
        return $this->filePath($this->generated(), $slug);
    }

    public function publishedFile(string $slug): string
    {
        return $this->filePath($this->published(), $slug);
    }

    private function pathFromConfig(string $key, string $fallback): string
    {
        $paths = $this->config['paths'] ?? [];
        if (!is_array($paths) || array_is_list($paths)) {
            return $fallback;
        }
        $value = $paths[$key] ?? $fallback;
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function resolvePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $path;
        }

        $path = str_replace('{root}', $this->root, $path);
        $path = str_replace('{$root}', $this->root, $path);

        if ($this->isAbsolute($path)) {
            return rtrim($path, '/\\');
        }

        return rtrim($this->root . '/' . ltrim($path, '/\\'), '/\\');
    }

    private function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }
        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    private function filePath(string $base, string $slug): string
    {
        $slug = trim(str_replace('\\', '/', $slug), '/');
        return rtrim($base, '/\\') . '/' . $slug . '.md';
    }

    private function unique(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $normalized = rtrim($path, '/\\');
            if ($normalized === '' || in_array($normalized, $result, true)) {
                continue;
            }
            $result[] = $normalized;
        }
        return $result;
    }
}

