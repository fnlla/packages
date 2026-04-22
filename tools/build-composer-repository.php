<?php

declare(strict_types=1);

$options = getopt('', ['version:', 'output::', 'dist-base-url::']);

$version = $options['version'] ?? getenv('RELEASE_VERSION') ?: detectVersionFromGitTag();
if ($version === null || trim($version) === '') {
    fwrite(STDERR, "Missing version. Use --version=3.0.4 or set RELEASE_VERSION.\n");
    exit(1);
}

$version = ltrim(trim($version), 'v');
$root = normalizePath((string) realpath(__DIR__ . '/..'));
if ($root === '') {
    fwrite(STDERR, "Unable to resolve repository root.\n");
    exit(1);
}

$outputDir = $options['output'] ?? ($root . DIRECTORY_SEPARATOR . 'repository');
$outputDir = normalizePath($outputDir);
$distDir = $outputDir . DIRECTORY_SEPARATOR . 'dist';
$distBaseUrl = isset($options['dist-base-url']) ? normalizeUrl((string) $options['dist-base-url']) : null;

recreateDirectory($outputDir);
createDirectory($distDir);

$packages = [];
$sourceDirectories = discoverPackageDirectories($root);

if ($sourceDirectories === []) {
    fwrite(STDERR, "No package directories discovered.\n");
    exit(1);
}

foreach ($sourceDirectories as $sourceDirectory) {
    $composerPath = $sourceDirectory . DIRECTORY_SEPARATOR . 'composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    if (!is_array($composer)) {
        fwrite(STDERR, "Invalid composer.json: {$composerPath}\n");
        exit(1);
    }

    $name = isset($composer['name']) ? (string) $composer['name'] : '';
    if ($name === '') {
        fwrite(STDERR, "Missing package name in {$composerPath}\n");
        exit(1);
    }

    $archiveType = class_exists('ZipArchive') ? 'zip' : 'tar';
    $archiveBasename = str_replace('/', '-', $name) . '-' . $version . '.' . $archiveType;
    $archiveRelative = 'dist/' . $archiveBasename;
    $archivePath = $distDir . DIRECTORY_SEPARATOR . $archiveBasename;
    $archiveUrl = $distBaseUrl !== null ? ($distBaseUrl . '/' . $archiveRelative) : $archiveRelative;

    createArchiveFromDirectory($sourceDirectory, $archivePath, $archiveType);

    $packageVersion = $composer;
    $packageVersion['version'] = $version;
    $packageVersion['dist'] = [
        'type' => $archiveType,
        'url' => $archiveUrl,
        'shasum' => sha1_file($archivePath) ?: '',
    ];

    if (!isset($packages[$name])) {
        $packages[$name] = [];
    }

    $packages[$name][$version] = $packageVersion;
}

ksort($packages);

$payload = [
    'packages' => $packages,
];

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'packages.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
);

file_put_contents(
    $outputDir . DIRECTORY_SEPARATOR . 'index.html',
    buildIndexHtml($packages, $version)
);

echo "Composer repository built in: {$outputDir}\n";
echo "Package count: " . count($packages) . "\n";
echo "Version: {$version}\n";

function normalizePath(string $path): string
{
    return rtrim($path, '\\/');
}

function normalizeUrl(string $url): string
{
    return rtrim(trim($url), '/');
}

function detectVersionFromGitTag(): ?string
{
    $output = [];
    $exitCode = 1;
    exec('git describe --tags --exact-match 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        return null;
    }

    $tag = trim(implode("\n", $output));
    if ($tag === '') {
        return null;
    }

    return ltrim($tag, 'v');
}

function discoverPackageDirectories(string $root): array
{
    $excluded = [
        '.git',
        '.github',
        '_shared',
        'repository',
        'tools',
    ];

    $directories = [];
    $entries = scandir($root);
    if ($entries === false) {
        return [];
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        if (in_array($entry, $excluded, true)) {
            continue;
        }

        if (str_starts_with($entry, '.')) {
            continue;
        }

        $absolutePath = $root . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($absolutePath)) {
            continue;
        }

        if (!is_file($absolutePath . DIRECTORY_SEPARATOR . 'composer.json')) {
            continue;
        }

        $directories[] = $absolutePath;
    }

    sort($directories);
    return $directories;
}

function recreateDirectory(string $path): void
{
    if (is_dir($path)) {
        removeDirectory($path);
    }
    createDirectory($path);
}

function createDirectory(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        fwrite(STDERR, "Unable to create directory: {$path}\n");
        exit(1);
    }
}

function removeDirectory(string $path): void
{
    $items = scandir($path);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $target = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($target)) {
            removeDirectory($target);
            continue;
        }

        unlink($target);
    }

    rmdir($path);
}

function createArchiveFromDirectory(string $sourceDirectory, string $archivePath, string $archiveType): void
{
    if ($archiveType === 'zip') {
        createZipFromDirectory($sourceDirectory, $archivePath);
        return;
    }

    createTarFromDirectory($sourceDirectory, $archivePath);
}

function createZipFromDirectory(string $sourceDirectory, string $zipPath): void
{
    $zip = new ZipArchive();
    $status = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($status !== true) {
        fwrite(STDERR, "Unable to create zip archive: {$zipPath}\n");
        exit(1);
    }

    $baseLength = strlen($sourceDirectory) + 1;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $absolutePath = $item->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, $baseLength));

        if (shouldSkipPath($relativePath)) {
            continue;
        }

        $zip->addFile($absolutePath, $relativePath);
    }

    $zip->close();
}

function createTarFromDirectory(string $sourceDirectory, string $tarPath): void
{
    if (file_exists($tarPath)) {
        unlink($tarPath);
    }

    try {
        $phar = new PharData($tarPath);
    } catch (Throwable $e) {
        fwrite(STDERR, "Unable to create tar archive: {$tarPath}\n");
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }

    $baseLength = strlen($sourceDirectory) + 1;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $absolutePath = $item->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, $baseLength));

        if (shouldSkipPath($relativePath)) {
            continue;
        }

        $phar->addFile($absolutePath, $relativePath);
    }

    unset($phar);
}

function shouldSkipPath(string $relativePath): bool
{
    return str_starts_with($relativePath, '.git/')
        || $relativePath === '.git'
        || str_contains($relativePath, '/.DS_Store')
        || str_starts_with($relativePath, 'vendor/');
}

function buildIndexHtml(array $packages, string $version): string
{
    $rows = [];
    foreach (array_keys($packages) as $packageName) {
        $rows[] = '<li><code>' . htmlspecialchars($packageName, ENT_QUOTES, 'UTF-8') . '</code></li>';
    }

    $list = implode("\n", $rows);
    $count = count($packages);

    return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>fnlla packages repository</title>
</head>
<body>
    <h1>fnlla composer repository</h1>
    <p>Version: <strong>{$version}</strong></p>
    <p>Packages: <strong>{$count}</strong></p>
    <ul>
{$list}
    </ul>
</body>
</html>
HTML;
}
