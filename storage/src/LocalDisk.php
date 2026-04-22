<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Storage;

final class LocalDisk implements DiskInterface
{
    public function __construct(
        private string $root,
        private string $urlPrefix
    ) {
        $this->root = rtrim($this->root, '/\\');
        $this->urlPrefix = rtrim($this->urlPrefix, '/');
    }

    public function path(string $path): string
    {
        return $this->root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function put(string $path, string $contents): bool
    {
        $target = $this->path($path);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($target, $contents) !== false;
    }

    public function putFile(string $path, string $sourcePath): bool
    {
        $target = $this->path($path);
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return copy($sourcePath, $target);
    }

    public function get(string $path): ?string
    {
        $target = $this->path($path);
        if (!is_file($target)) {
            return null;
        }
        $contents = file_get_contents($target);
        return $contents === false ? null : $contents;
    }

    public function exists(string $path): bool
    {
        return is_file($this->path($path));
    }

    public function delete(string $path): bool
    {
        $target = $this->path($path);
        if (!is_file($target)) {
            return false;
        }
        return unlink($target);
    }

    public function url(string $path): string
    {
        $path = ltrim($path, '/');
        return $this->urlPrefix === '' ? '/' . $path : $this->urlPrefix . '/' . $path;
    }
}
