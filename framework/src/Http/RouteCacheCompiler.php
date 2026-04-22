<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Core\Application;
use Fnlla\Core\ConfigRepository;
use Fnlla\Core\ConfigValidator;
use Fnlla\Plugin\PluginManager;
use RuntimeException;

/**
 * Compiles route definitions into a cache file.
 *
 * @api
 */
final class RouteCacheCompiler
{
    public function compile(?string $appRoot = null, ?Application $app = null, ?string $cachePath = null): string
    {
        [$root, $app, $appConfig, $config] = $this->resolveAppAndConfig($appRoot, $app);

        $this->bootstrapApp($app, $appConfig, $config);

        $basePath = (string) ($config['base_path'] ?? '');
        $router = new Router($basePath, $app);

        $httpConfig = $config['http'] ?? [];
        if (is_array($httpConfig)) {
            $groups = $httpConfig['middleware_groups'] ?? [];
            if (is_array($groups)) {
                foreach ($groups as $name => $group) {
                    if (!is_array($group)) {
                        $group = [$group];
                    }
                    $router->middlewareGroup((string) $name, $group);
                }
            }

            $aliases = $httpConfig['middleware_aliases'] ?? [];
            if (is_array($aliases) && $aliases !== []) {
                $router->middlewareAliases($aliases);
            }
        }

        $this->loadRoutesFile($router, $root . '/routes/web.php');

        $path = $cachePath ?? (string) ($config['routes_cache'] ?? '');
        if ($path === '') {
            $path = $root . '/storage/cache/routes.php';
        }

        $strict = true;
        if (array_key_exists('routes_cache_strict', $config)) {
            $strict = (bool) $config['routes_cache_strict'];
        }

        $issues = $router->cacheIssues();
        if ($issues !== []) {
            if ($strict) {
                $summary = $issues[0];
                if (count($issues) > 1) {
                    $summary .= ' (+' . (count($issues) - 1) . ' more).';
                }
                throw new RuntimeException('Routes cache failed: ' . $summary);
            }

            $this->writeDisabledCacheFile($path, $issues);
            return $path;
        }

        $routes = $router->dumpRoutes();
        $this->writeCacheFile($path, $routes);

        return $path;
    }

    private function resolveAppAndConfig(?string $appRoot, ?Application $app): array
    {
        $app = $app instanceof Application ? $app : null;
        $root = $app instanceof Application
            ? $app->basePath()
            : ($appRoot ?? (defined('APP_ROOT') ? APP_ROOT : ConfigRepository::resolveAppRoot()));

        if ($app instanceof Application) {
            $repository = $app->configRepository();
            $appConfig = $app->config();
        } else {
            $repository = ConfigRepository::fromRoot($root);
            $app = new Application($root, $repository);
            $appConfig = $app->config();
        }

        $config = $repository->all();

        return [$root, $app, $appConfig, $config];
    }

    private function bootstrapApp(Application $app, ConfigRepository $appConfig, array $config): void
    {
        $timezone = (string) ($config['timezone'] ?? 'UTC');
        if ($timezone !== '') {
            date_default_timezone_set($timezone);
        }

        $schema = $config['schema'] ?? null;
        if (is_array($schema) && $schema !== []) {
            ConfigValidator::assertValid($config, $schema);
        }

        $providers = $config['providers'] ?? [];
        if (is_array($providers) && $providers !== []) {
            $app->registerProviders($providers);
            $app->bootProviders();
        }

        $pluginManager = new PluginManager($appConfig, $app);
        $app->instance(PluginManager::class, $pluginManager);
        $pluginList = $config['plugins'] ?? [];
        if (is_array($pluginList) && $pluginList !== []) {
            $pluginManager->load($pluginList);
            $pluginManager->boot();
        }
    }

    private function loadRoutesFile(Router $router, string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $routes = require $file;

        if (is_callable($routes)) {
            $routes($router);
            return;
        }

        if (is_array($routes)) {
            foreach ($routes as $route) {
                [$method, $pattern, $handler, $name, $middleware, $host] = array_pad($route, 6, null);
                $router->add($method, $pattern, $handler, $name, $middleware ?? [], $host);
            }
        }
    }

    private function writeCacheFile(string $path, array $routes): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create routes cache directory: ' . $dir);
        }

        $payload = "<?php\n\nreturn " . var_export($routes, true) . ";\n";
        if (file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write routes cache: ' . $path);
        }
    }

    private function writeDisabledCacheFile(string $path, array $issues): void
    {
        $summary = $issues[0] ?? 'Non-cacheable routes detected.';
        if (count($issues) > 1) {
            $summary .= ' (+' . (count($issues) - 1) . ' more).';
        }

        $payload = [
            '__Fnlla_routes_cache' => [
                'disabled' => true,
                'reason' => $summary,
                'issues' => array_slice($issues, 0, 10),
            ],
        ];

        $this->writeCacheFile($path, $payload);
    }
}
