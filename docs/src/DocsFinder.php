<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

final class DocsFinder
{
    /** @var string[] */
    private array $paths;

    /**
     * @param string[] $paths
     */
    public function __construct(array $paths)
    {
        $normalized = [];
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }
            $path = rtrim($path, '/\\');
            if ($path === '' || in_array($path, $normalized, true)) {
                continue;
            }
            $normalized[] = $path;
        }
        $this->paths = $normalized;
    }

    public function resolve(string $slug): ?string
    {
        $slug = DocsSlug::normalize($slug);
        if ($slug === null) {
            return null;
        }

        foreach ($this->paths as $base) {
            $resolved = $this->resolveInBase($base, $slug);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    public function read(string $slug): ?string
    {
        $path = $this->resolve($slug);
        if ($path === null || !is_file($path)) {
            return null;
        }
        return (string) file_get_contents($path);
    }

    public function listDocs(): array
    {
        $items = [];

        foreach ($this->paths as $base) {
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                if (strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $relative = substr($file->getPathname(), strlen($base) + 1);
                $relative = str_replace('\\', '/', $relative);

                $slug = null;
                if (preg_match('/^(.*)\/readme\.md$/i', $relative, $matches) === 1) {
                    $slug = (string) $matches[1];
                } elseif (preg_match('/^(.*)\/index\.md$/i', $relative, $matches) === 1) {
                    $slug = (string) $matches[1];
                } else {
                    $slug = preg_replace('/\.md$/i', '', $relative);
                }

                if (!is_string($slug) || $slug === '' || $slug === 'index') {
                    continue;
                }

                if (isset($items[$slug])) {
                    continue;
                }

                $title = $this->titleFromMarkdown((string) file_get_contents($file->getPathname()), $slug);
                $items[$slug] = ['slug' => $slug, 'title' => $title, 'path' => $file->getPathname()];
            }
        }

        $items = array_values($items);
        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['title'], (string) $b['title']));
        return $items;
    }

    private function resolveInBase(string $base, string $slug): ?string
    {
        $base = rtrim($base, '/\\');
        $baseFile = $base . '/' . $slug;

        $candidates = [
            $baseFile . '.md',
            $baseFile . '/index.md',
            $baseFile . '/README.md',
            $baseFile . '/readme.md',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function titleFromMarkdown(string $contents, string $fallback): string
    {
        $lines = preg_split('/\r?\n/', $contents) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '# ')) {
                return trim(substr($line, 2));
            }
        }
        return $fallback;
    }
}


