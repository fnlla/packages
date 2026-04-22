<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Docs\DocsManager;
use Fnlla\Docs\DocsMarkdownRenderer;
use Fnlla\Docs\DocsPaths;

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fnlla-docs-smoke-' . uniqid();
@mkdir($root, 0777, true);

$config = new ConfigRepository([
    'docs' => [
        'paths' => [
            'manual' => $root . '/manual',
            'generated' => $root . '/generated',
            'published' => $root . '/published',
        ],
    ],
]);

$paths = new DocsPaths($config, $root);
$manager = new DocsManager($config, $paths);
$report = $manager->generate();

$overview = $root . '/generated/technical/overview.md';
if (!is_file($overview)) {
    fwrite(STDERR, "FAIL: overview doc not generated\n");
    exit(1);
}

if (!is_array($report) || ($report['generated'] ?? []) === []) {
    fwrite(STDERR, "FAIL: generation report is empty\n");
    exit(1);
}

$manual = $root . '/manual/technical/overview.md';
@mkdir(dirname($manual), 0777, true);
file_put_contents($manual, "# Manual Overview\n\nManual override\n");

$publish = $manager->publish();
$published = $root . '/published/technical/overview.md';
if (!is_file($published)) {
    fwrite(STDERR, "FAIL: publish did not create overview doc\n");
    exit(1);
}

$publishedContents = (string) file_get_contents($published);
if (!str_contains($publishedContents, 'Manual override')) {
    fwrite(STDERR, "FAIL: publish did not apply manual override\n");
    exit(1);
}

if (!is_array($publish) || ($publish['published'] ?? []) === []) {
    fwrite(STDERR, "FAIL: publish report is empty\n");
    exit(1);
}

$renderer = new DocsMarkdownRenderer();
$html = $renderer->toHtml("# Heading\n\n- one\n- two\n\n[Home](/docs)");
if (!str_contains($html, '<h1') || !str_contains($html, '<ul>') || !str_contains($html, '<a href="/docs">')) {
    fwrite(STDERR, "FAIL: markdown renderer output unexpected\n");
    exit(1);
}

$unsafe = $renderer->toHtml('[Bad](javascript:alert(1)) [Data](data:text/html;base64,WA==) [OK](https://example.com)');
if (str_contains(strtolower($unsafe), 'javascript:') || str_contains(strtolower($unsafe), 'data:text/html')) {
    fwrite(STDERR, "FAIL: unsafe markdown links should be stripped\n");
    exit(1);
}

if (!str_contains($unsafe, '<a href="https://example.com" rel="noopener noreferrer">OK</a>')) {
    fwrite(STDERR, "FAIL: safe external markdown link should be rendered\n");
    exit(1);
}

echo "Docs smoke tests OK\n";
