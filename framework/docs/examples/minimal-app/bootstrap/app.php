<?php

declare(strict_types=1);

use Fnlla\Core\Application;
use Fnlla\Core\ConfigRepository;
use Fnlla\Http\HttpKernel;
use Fnlla\Support\ComposerProviderDiscovery;
use Fnlla\Support\ProviderCache;
use Fnlla\Support\ProviderRepository;

$root = dirname(__DIR__);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}

$envPath = $root . '/.env';
if (file_exists($envPath)) {
    (function ($path) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (!str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            putenv(trim($k) . '=' . trim($v));
        }
    })($envPath);
}

$configRepo = ConfigRepository::fromRoot($root);
$app = new Application($root, $configRepo);
$GLOBALS['Fnlla_app'] = $app;

$providerConfig = $configRepo->get('providers', []);
if (!is_array($providerConfig)) {
    $providerConfig = [];
}

$autoDiscovery = (bool) ($providerConfig['auto_discovery'] ?? true);
$disabled = $providerConfig['disabled'] ?? [];
if (!is_array($disabled)) {
    $disabled = [];
}
$manual = $providerConfig['manual'] ?? [];
if (!is_array($manual)) {
    $manual = [];
}

$cachePath = $root . '/bootstrap/cache/providers.php';
$discovered = [];
$discoveredMeta = [];
if (is_file($cachePath)) {
    $cached = require $cachePath;
    if (is_array($cached)) {
        if (isset($cached['providers']) || isset($cached['meta'])) {
            $discovered = is_array($cached['providers'] ?? null) ? $cached['providers'] : [];
            $discoveredMeta = is_array($cached['meta'] ?? null) ? $cached['meta'] : [];
        } else {
            $discovered = $cached;
        }
    }
} elseif ($autoDiscovery) {
    $discovery = ComposerProviderDiscovery::discover($root . '/vendor');
    $discovered = is_array($discovery['providers'] ?? null) ? $discovery['providers'] : [];
    $discoveredMeta = is_array($discovery['meta'] ?? null) ? $discovery['meta'] : [];
    ProviderCache::write($cachePath, $discovery);
}

$env = strtolower((string) getenv('APP_ENV'));
if ($env === '') {
    $env = 'prod';
}

$providers = [];
$providerMeta = [];
foreach ($discovered as $provider) {
    if (!is_string($provider) || $provider === '') {
        continue;
    }

    $rules = $disabled[$provider] ?? null;
    if (is_array($rules)) {
        if (!empty($rules['all'])) {
            continue;
        }
        if (isset($rules[$env]) && $rules[$env]) {
            continue;
        }
    }

    $providers[] = $provider;
    $providerMeta[$provider] = is_array($discoveredMeta[$provider] ?? null) ? $discoveredMeta[$provider] : ['source' => 'auto'];
}

foreach ($manual as $provider) {
    if (is_string($provider) && $provider !== '') {
        $providers[] = $provider;
        $providerMeta[$provider] = ['source' => 'manual'];
    }
}

$providers = array_values(array_unique($providers));
$repository = new ProviderRepository($app);
foreach ($providers as $provider) {
    $meta = $providerMeta[$provider] ?? [];
    $source = (string) ($meta['source'] ?? 'auto');
    $repository->add($provider, 0, $source, true, $meta);
}
$repository->registerAll();
$repository->bootAll();

return new HttpKernel($app);
