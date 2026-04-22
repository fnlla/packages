<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Content;

final class ContentRepository
{
    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($this->basePath, '/\\');
    }

    public function get(string $slug): ?ContentItem
    {
        $slug = trim($slug);
        if ($slug === '' || str_contains($slug, '..') || str_starts_with($slug, '/')) {
            return null;
        }

        $paths = [
            $this->basePath . '/' . $slug . '.md',
            $this->basePath . '/' . $slug . '.json',
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                return $this->loadFromPath($slug, $path);
            }
        }

        return null;
    }

    public function all(): array
    {
        if (!is_dir($this->basePath)) {
            return [];
        }

        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if ($ext !== 'md' && $ext !== 'json') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($this->basePath) + 1);
            $slug = preg_replace('/\.(md|json)$/i', '', str_replace('\\', '/', $relative));
            if (!is_string($slug) || $slug === '') {
                continue;
            }

            $item = $this->loadFromPath($slug, $file->getPathname());
            if ($item instanceof ContentItem) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function loadFromPath(string $slug, string $path): ?ContentItem
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $payload = json_decode((string) file_get_contents($path), true);
            if (!is_array($payload)) {
                return null;
            }
            return new ContentItem($slug, $payload, $payload['body'] ?? '', $path);
        }

        if ($ext !== 'md') {
            return null;
        }

        $contents = (string) file_get_contents($path);
        [$meta, $body] = $this->parseMarkdown($contents);
        return new ContentItem($slug, $meta, $body, $path);
    }

    private function parseMarkdown(string $contents): array
    {
        $meta = [];
        $body = $contents;

        $lines = preg_split('/\r?\n/', $contents) ?: [];
        if (($lines[0] ?? null) !== '---') {
            return [$meta, $body];
        }

        $endIndex = null;
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '---') {
                $endIndex = $i;
                break;
            }
        }

        if ($endIndex === null) {
            return [$meta, $body];
        }

        $frontMatter = trim(implode("\n", array_slice($lines, 1, $endIndex - 1)));
        $body = implode("\n", array_slice($lines, $endIndex + 1));

        if ($frontMatter !== '') {
            $decoded = json_decode($frontMatter, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            } else {
                $meta = $this->parseKeyValue($frontMatter);
            }
        }

        return [$meta, $body];
    }

    private function parseKeyValue(string $frontMatter): array
    {
        $meta = [];
        $lines = preg_split('/\r?\n/', $frontMatter) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key !== '') {
                $meta[$key] = $value;
            }
        }
        return $meta;
    }
}
