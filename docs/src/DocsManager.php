<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Application;

final class DocsManager
{
    private string $root;

    public function __construct(private ConfigRepository $config, private DocsPaths $paths)
    {
        $this->root = $this->paths->root();
    }

    public function generate(array $options = []): array
    {
        $target = $options['target'] ?? $this->paths->generated();
        if (!is_string($target) || trim($target) === '') {
            $target = $this->paths->generated();
        }
        $target = rtrim($target, '/\\');

        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $data = $this->collectSnapshot();

        $pages = $this->buildPages($data, $timestamp);
        $files = [];

        foreach ($pages as $page) {
            $slug = $page['slug'] ?? null;
            $content = $page['content'] ?? null;
            if (!is_string($slug) || !is_string($content) || $slug === '') {
                continue;
            }
            $files[] = $this->writeDoc($target, $slug, $content);
        }

        $this->ensureDir($this->paths->manual());

        return [
            'target' => $target,
            'generated' => array_values(array_filter($files)),
            'timestamp' => $timestamp,
        ];
    }

    public function publish(array $options = []): array
    {
        $source = $options['source'] ?? $this->paths->generated();
        if (!is_string($source) || trim($source) === '') {
            $source = $this->paths->generated();
        }
        $target = $options['target'] ?? $this->paths->published();
        if (!is_string($target) || trim($target) === '') {
            $target = $this->paths->published();
        }
        $includeManual = $options['include_manual'] ?? true;
        $includeManual = is_bool($includeManual) ? $includeManual : true;

        $source = rtrim($source, '/\\');
        $target = rtrim($target, '/\\');
        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';

        $generated = $this->collectDocs($source);
        $manual = $includeManual ? $this->collectDocs($this->paths->manual()) : [];

        $slugs = array_unique(array_merge(array_keys($generated), array_keys($manual)));
        sort($slugs);

        $published = [];
        foreach ($slugs as $slug) {
            $path = $manual[$slug] ?? $generated[$slug] ?? null;
            if ($path === null) {
                continue;
            }
            $content = file_get_contents($path);
            if (!is_string($content)) {
                continue;
            }
            $written = $this->writeDoc($target, $slug, $content);
            if ($written === '') {
                continue;
            }
            $published[] = [
                'slug' => $slug,
                'path' => $written,
                'source' => isset($manual[$slug]) ? 'manual' : 'generated',
            ];
        }

        return [
            'source' => $source,
            'target' => $target,
            'timestamp' => $timestamp,
            'published' => $published,
        ];
    }

    public function paths(): DocsPaths
    {
        return $this->paths;
    }

    private function collectSnapshot(): array
    {
        return [
            'app' => $this->collectAppInfo(),
            'runtime' => $this->collectRuntimeInfo(),
            'packages' => $this->collectPackages(),
            'config' => $this->collectConfigFiles(),
            'routes' => $this->collectRouteFiles(),
            'env' => $this->collectEnvKeys(),
        ];
    }

    private function collectAppInfo(): array
    {
        $name = (string) $this->config->get('app.name', 'fnlla');
        $env = (string) $this->config->get('app.env', 'local');
        $version = (string) $this->config->get('app.version', 'dev');
        $basePath = (string) $this->config->get('app.base_path', '');
        $timezone = (string) $this->config->get('app.timezone', 'UTC');
        $url = getenv('APP_URL');
        $root = str_replace('\\', '/', $this->root);
        $rootLabel = basename(rtrim($root, '/'));
        if ($rootLabel === '' || $rootLabel === '.' || $rootLabel === '..') {
            $rootLabel = '[app-root]';
        }

        return [
            'name' => $name,
            'env' => $env,
            'version' => $version,
            'base_path' => $basePath,
            'timezone' => $timezone,
            'url' => is_string($url) ? $url : '',
            'root' => $rootLabel,
        ];
    }

    private function collectRuntimeInfo(): array
    {
        return [
            'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'fnlla' => Application::VERSION,
        ];
    }

    private function collectPackages(): array
    {
        $lock = $this->readJson($this->root . '/composer.lock');
        $packages = is_array($lock['packages'] ?? null) ? $lock['packages'] : [];
        $devPackages = is_array($lock['packages-dev'] ?? null) ? $lock['packages-dev'] : [];
        $all = array_merge($packages, $devPackages);

        $fnlla = [];
        $thirdParty = [];

        foreach ($all as $pkg) {
            if (!is_array($pkg)) {
                continue;
            }
            $name = (string) ($pkg['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $version = (string) ($pkg['version'] ?? '');
            $label = $version !== '' ? $name . ' (' . $version . ')' : $name;

            if (str_starts_with($name, 'fnlla/')) {
                $fnlla[$name] = $label;
            } else {
                $thirdParty[$name] = $label;
            }
        }

        ksort($fnlla);
        ksort($thirdParty);

        return [
            'fnlla' => array_values($fnlla),
            'third_party' => array_values($thirdParty),
            'total' => count($all),
        ];
    }

    private function collectConfigFiles(): array
    {
        $dir = $this->root . '/config';
        if (!is_dir($dir)) {
            return [];
        }
        $items = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!is_string($path) || $path === '' || !str_ends_with(strtolower($path), '.php')) {
                continue;
            }
            $relative = substr($path, strlen($dir) + 1);
            if (!is_string($relative) || $relative === '') {
                continue;
            }
            $items[] = 'config/' . str_replace('\\', '/', $relative);
        }

        sort($items);
        return $items;
    }

    private function collectRouteFiles(): array
    {
        $dir = $this->root . '/routes';
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.php') ?: [];
        $items = [];
        foreach ($files as $file) {
            $count = $this->countRoutesInFile($file);
            $items[] = [
                'file' => 'routes/' . basename($file),
                'routes' => $count,
            ];
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['file'], (string) $b['file']));
        return $items;
    }

    private function collectEnvKeys(): array
    {
        $path = $this->root . '/.env.example';
        if (!is_file($path)) {
            return [];
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $keys = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '' || preg_match('/^[A-Z0-9_]+$/', $key) !== 1) {
                continue;
            }
            $keys[] = $key;
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
        return $this->groupEnvKeys($keys);
    }

    private function groupEnvKeys(array $keys): array
    {
        $groups = [];
        foreach ($keys as $key) {
            $parts = explode('_', $key, 2);
            $group = strtoupper($parts[0] ?? 'MISC');
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = $key;
        }
        ksort($groups);
        return $groups;
    }

    private function buildPages(array $data, string $timestamp): array
    {
        $pages = [];
        $pages[] = [
            'slug' => 'technical/overview',
            'content' => $this->renderOverview($data, $timestamp),
        ];
        $pages[] = [
            'slug' => 'technical/inventory',
            'content' => $this->renderInventory($data, $timestamp),
        ];
        $pages[] = [
            'slug' => 'technical/integrations',
            'content' => $this->renderIntegrations($data, $timestamp),
        ];
        $pages[] = [
            'slug' => 'user/guide',
            'content' => $this->renderUserGuide($data, $timestamp),
        ];
        $pages[] = [
            'slug' => 'user/faq',
            'content' => $this->renderUserFaq($data, $timestamp),
        ];

        return $pages;
    }

    private function renderOverview(array $data, string $timestamp): string
    {
        $app = $data['app'] ?? [];
        $runtime = $data['runtime'] ?? [];
        $packages = $data['packages']['fnlla'] ?? [];

        $lines = [];
        $lines[] = '# Technical Overview';
        $lines[] = '';
        $lines[] = '## Application';
        $lines[] = '- Name: ' . $this->value($app['name'] ?? '');
        $lines[] = '- Environment: ' . $this->value($app['env'] ?? '');
        $lines[] = '- Version: ' . $this->value($app['version'] ?? '');
        $lines[] = '- Base path: ' . $this->value($app['base_path'] ?? '');
        $lines[] = '- Timezone: ' . $this->value($app['timezone'] ?? '');
        if (($app['url'] ?? '') !== '') {
            $lines[] = '- App URL: ' . $this->value($app['url'] ?? '');
        }
        $lines[] = '- Root: ' . $this->value($app['root'] ?? '');
        $lines[] = '';
        $lines[] = '## Runtime';
        $lines[] = '- PHP: ' . $this->value($runtime['php'] ?? '');
        $lines[] = '- fnlla: ' . $this->value($runtime['fnlla'] ?? '');
        $lines[] = '';
        $lines[] = '## fnlla Packages';
        $lines[] = $this->renderList($packages, '- No fnlla packages detected.');

        return implode("\n", $lines) . "\n";
    }

    private function renderInventory(array $data, string $timestamp): string
    {
        $config = $data['config'] ?? [];
        $routes = $data['routes'] ?? [];
        $packages = $data['packages'] ?? [];
        $thirdParty = $packages['third_party'] ?? [];

        $lines = [];
        $lines[] = '# Technical Inventory';
        $lines[] = '';
        $lines[] = '## Config Files';
        $lines[] = $this->renderList($config, '- No config files detected.');
        $lines[] = '';
        $lines[] = '## Route Files';
        if ($routes === []) {
            $lines[] = '- No route files detected.';
        } else {
            foreach ($routes as $route) {
                $file = $route['file'] ?? '';
                $count = $route['routes'] ?? 0;
                $lines[] = '- ' . $file . ' (' . (int) $count . ' route statements)';
            }
        }
        $lines[] = '';
        $lines[] = '## Third-Party Packages';
        $lines[] = 'Total: ' . (string) ($packages['total'] ?? 0);
        $lines[] = $this->renderList($thirdParty, '- No third-party packages detected.');

        return implode("\n", $lines) . "\n";
    }

    private function renderIntegrations(array $data, string $timestamp): string
    {
        $env = $data['env'] ?? [];

        $lines = [];
        $lines[] = '# Integrations & Environment';
        $lines[] = '';
        if ($env === []) {
            $lines[] = 'No environment keys detected (missing `.env.example`).';
            return implode("\n", $lines) . "\n";
        }

        $groups = array_keys($env);
        $lastGroup = count($groups) - 1;
        foreach ($groups as $index => $group) {
            $keys = is_array($env[$group] ?? null) ? $env[$group] : [];
            $lines[] = '## ' . $group;
            $lines[] = $this->renderList($keys, '- (none)');
            if ($index < $lastGroup) {
                $lines[] = '';
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function renderUserGuide(array $data, string $timestamp): string
    {
        $appName = (string) ($data['app']['name'] ?? 'Your App');

        $lines = [];
        $lines[] = '# User Guide (Draft)';
        $lines[] = '';
        $lines[] = '## Purpose';
        $lines[] = 'Describe what ' . $appName . ' does for end users.';
        $lines[] = '';
        $lines[] = '## Getting Started';
        $lines[] = '- How users sign in.';
        $lines[] = '- First-time setup steps.';
        $lines[] = '';
        $lines[] = '## Core Features';
        $lines[] = '- List the main workflows.';
        $lines[] = '- Include links to UI sections.';
        $lines[] = '';
        $lines[] = '## Troubleshooting';
        $lines[] = '- Common issues and resolutions.';
        $lines[] = '';
        $lines[] = '## Support';
        $lines[] = '- Where users should report problems.';

        return implode("\n", $lines) . "\n";
    }

    private function renderUserFaq(array $data, string $timestamp): string
    {
        $lines = [];
        $lines[] = '# User FAQ (Draft)';
        $lines[] = '';
        $lines[] = '## Common Questions';
        $lines[] = '- How do I reset my password?';
        $lines[] = '- Where do I find my invoices?';
        $lines[] = '- Who can I contact for help?';
        $lines[] = '';
        $lines[] = '## Feature Notes';
        $lines[] = '- Add clarifications for edge cases.';

        return implode("\n", $lines) . "\n";
    }

    private function renderList(array $items, string $emptyLine): string
    {
        if ($items === []) {
            return $emptyLine;
        }

        $lines = [];
        foreach ($items as $item) {
            $lines[] = '- ' . (string) $item;
        }
        return implode("\n", $lines);
    }

    private function writeDoc(string $base, string $slug, string $content): string
    {
        $slug = trim(str_replace('\\', '/', $slug), '/');
        if ($slug === '') {
            return '';
        }
        $path = rtrim($base, '/\\') . '/' . $slug . '.md';
        $dir = dirname($path);
        $this->ensureDir($dir);
        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('Unable to write docs file: ' . $path);
        }
        return $path;
    }

    private function ensureDir(string $dir): void
    {
        if ($dir === '' || is_dir($dir)) {
            return;
        }
        @mkdir($dir, 0775, true);
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return [];
        }
        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    private function collectDocs(string $base): array
    {
        if ($base === '' || !is_dir($base)) {
            return [];
        }

        $base = rtrim($base, '/\\');
        $docs = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (!str_ends_with(strtolower($path), '.md')) {
                continue;
            }
            $relative = substr($path, strlen($base) + 1);
            if (!is_string($relative) || $relative === '') {
                continue;
            }
            $relative = str_replace('\\', '/', $relative);
            if (!str_ends_with($relative, '.md')) {
                continue;
            }
            $slug = substr($relative, 0, -3);
            if ($slug === '') {
                continue;
            }
            if (str_ends_with($slug, '/index')) {
                $slug = substr($slug, 0, -6);
                if ($slug === '') {
                    continue;
                }
            }
            $docs[$slug] = $path;
        }

        return $docs;
    }

    private function countRoutesInFile(string $path): int
    {
        if (!is_file($path)) {
            return 0;
        }
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return 0;
        }
        if (preg_match_all('/\$router->(get|post|put|patch|delete|options|add|group)\s*\(/i', $contents, $matches) !== false) {
            return count($matches[0]);
        }
        return 0;
    }

    private function value(string $value): string
    {
        return $value === '' ? '-' : $value;
    }
}
