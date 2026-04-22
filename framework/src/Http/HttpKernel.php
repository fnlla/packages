<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Core\ConfigValidator;
use Fnlla\Contracts\Http\KernelInterface;
use Fnlla\Core\Application;
use Fnlla\Core\ConfigRepository;
use Fnlla\Core\ExceptionHandler;
use Fnlla\Runtime\RequestContext;
use Fnlla\Runtime\ResetManager;
use Fnlla\Runtime\Profiler;
use Fnlla\Plugin\PluginManager;
use Throwable;

/**
 * Core HTTP kernel: bootstraps configuration, providers, and dispatches requests.
 *
 * @api
 */
final class HttpKernel implements KernelInterface
{
    private bool $booted = false;
    private ?Application $bootedApp = null;
    private ?ConfigRepository $bootedConfig = null;
    private array $bootedConfigArray = [];
    private string $bootedRoot = '';
    private bool $bootedDebug = false;
    private string $bootedEnv = '';

    public function __construct(private ?Application $app = null, private bool $bootstrap = true)
    {
    }

    public function boot(?string $appRoot = null): void
    {
        if ($this->booted) {
            return;
        }

        [$resolvedRoot, $app, $appConfig, $config, $debug, $env] = $this->resolveAppAndConfig($appRoot);

        if ($this->bootstrap) {
            $this->bootstrapApp($app, $appConfig, $config);
        }

        $this->booted = true;
        $this->bootedRoot = $resolvedRoot;
        $this->bootedApp = $app;
        $this->bootedConfig = $appConfig;
        $this->bootedConfigArray = $config;
        $this->bootedDebug = $debug;
        $this->bootedEnv = $env;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        $resetManager = new ResetManager();
        $requestId = trim($request->getHeaderLine('X-Request-Id'));
        $traceId = trim($request->getHeaderLine('X-Trace-Id'));
        $spanId = trim($request->getHeaderLine('X-Span-Id'));
        $context = new RequestContext($resetManager, $requestId, $startTime, null, null, $traceId, $spanId);
        $context->begin();
        $handler = null;
        $restoreErrorHandler = false;
        $app = null;
        $appConfig = null;
        $config = [];
        $profiler = null;
        $debug = false;
        $env = '';

        try {
            if ($this->booted) {
                $app = $this->bootedApp;
                $appConfig = $this->bootedConfig;
                $config = $this->bootedConfigArray;
                $debug = $this->bootedDebug;
                $env = $this->bootedEnv;
                $appRoot = $this->bootedRoot;
            } else {
                [$appRoot, $app, $appConfig, $config, $debug, $env] = $this->resolveAppAndConfig();
                if ($this->bootstrap) {
                    $this->bootstrapApp($app, $appConfig, $config);
                }
            }

            $locale = $config['locale'] ?? null;
            if (is_string($locale) && trim($locale) !== '') {
                $context->setLocale($locale);
            }

            $resetManager->register($app);
            foreach ($app->resetters() as $resetter) {
                $resetManager->register($resetter);
            }
            $app->scopedInstance(RequestContext::class, $context);
            $profiler = Profiler::start($startTime);
            $app->scopedInstance(Profiler::class, $profiler);

            $handler = new ExceptionHandler($debug, $app, $appConfig, $context);
            set_error_handler([$handler, 'handleError']);
            $restoreErrorHandler = true;

            $basePath = (string) ($config['base_path'] ?? '');
            $trustedProxyConfig = $config['trusted_proxies'] ?? [];
            if (!is_array($trustedProxyConfig)) {
                $trustedProxyConfig = [];
            }
            $request = Request::fromPsr($request, $basePath, $trustedProxyConfig);
            $router = new Router($basePath, $app);
            $app->scopedInstance(Request::class, $request);
            $app->scopedInstance(Router::class, $router);
            $app->scopedInstance('request', $request);
            $app->scopedInstance('router', $router);

            $httpConfig = $config['http'] ?? [];
            $includeRequestIdHeader = true;
            $includeTraceIdHeader = true;
            $includeSpanIdHeader = true;
            if (is_array($httpConfig)) {
                if (array_key_exists('request_id_header', $httpConfig)) {
                    $includeRequestIdHeader = (bool) $httpConfig['request_id_header'];
                }
                if (array_key_exists('trace_id_header', $httpConfig)) {
                    $includeTraceIdHeader = (bool) $httpConfig['trace_id_header'];
                }
                if (array_key_exists('span_id_header', $httpConfig)) {
                    $includeSpanIdHeader = (bool) $httpConfig['span_id_header'];
                }
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

                $global = $httpConfig['global'] ?? [];
                if (is_array($global)) {
                    foreach ($global as $middleware) {
                        $router->use($middleware);
                    }
                }
            }
            $context->setHeaderFlags($includeRequestIdHeader, $includeTraceIdHeader, $includeSpanIdHeader);

            $loadRoutesFile = function (string $file) use ($router): void {
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
            };

            $routesCache = (string) ($config['routes_cache'] ?? '');
            if ($routesCache === '') {
                $routesCache = $appRoot . '/storage/cache/routes.php';
            }
            $performance = $config['performance'] ?? [];
            if (!is_array($performance)) {
                $performance = [];
            }
            $routesCacheEnabled = true;
            if (array_key_exists('routes_cache_enabled', $performance)) {
                $routesCacheEnabled = (bool) $performance['routes_cache_enabled'];
            }
            $routesCacheEnvs = $performance['routes_cache_envs'] ?? [];
            if (!is_array($routesCacheEnvs)) {
                $routesCacheEnvs = [];
            }
            $envAllowsCache = $routesCacheEnvs === [] || in_array($env, $routesCacheEnvs, true);
            $useRoutesCache = $routesCacheEnabled && $envAllowsCache && is_file($routesCache) && !$debug;

            if ($useRoutesCache) {
                $cached = require $routesCache;
                if (is_array($cached) && array_key_exists('__Fnlla_routes_cache', $cached)) {
                    $meta = $cached['__Fnlla_routes_cache'];
                    $disabled = is_array($meta) && !empty($meta['disabled']);
                    if ($disabled) {
                        $reason = 'unknown';
                        if (is_array($meta) && isset($meta['reason']) && is_string($meta['reason'])) {
                            $reason = $meta['reason'];
                        }
                        error_log('Routes cache disabled: ' . $reason);
                        $cached = null;
                    }
                }
                if (is_array($cached)) {
                    $error = null;
                    if (!$router->isValidRoutesCache($cached, $error)) {
                        if ($debug) {
                            throw new \RuntimeException('Invalid routes cache: ' . ($error ?? 'unknown error'));
                        }
                        error_log('Invalid routes cache ignored: ' . ($error ?? 'unknown error'));
                        $cached = null;
                    }
                }
                if (is_array($cached)) {
                    $router->loadRoutes($cached);
                }
            } else {
                $loadRoutesFile($appRoot . '/routes/web.php');
            }

            $response = $router->dispatch($request);
            $response = $response->withBasePath($basePath);
            if ($includeRequestIdHeader && !$response->hasHeader('X-Request-Id')) {
                $response = $response->withHeader('X-Request-Id', $context->requestId());
            }
            if ($includeTraceIdHeader && !$response->hasHeader('X-Trace-Id')) {
                $response = $response->withHeader('X-Trace-Id', $context->traceId());
            }
            if ($includeSpanIdHeader && !$response->hasHeader('X-Span-Id')) {
                $response = $response->withHeader('X-Span-Id', $context->spanId());
            }
            return $response;
        } catch (Throwable $exception) {
            if (!$handler instanceof ExceptionHandler) {
                $handler = new ExceptionHandler($debug, $app, $appConfig, $context);
            }
            $handler->report($exception);
            return $handler->render($exception, $request);
        } finally {
            if ($restoreErrorHandler) {
                restore_error_handler();
            }
            $context->end();
            Profiler::stop();
        }
    }

    private function resolveAppAndConfig(?string $appRoot = null): array
    {
        $app = $this->app instanceof Application ? $this->app : null;
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

        $debug = (bool) ($config['debug'] ?? ($config['app']['debug'] ?? false));
        $env = (string) ($config['env'] ?? ($config['app']['env'] ?? ''));
        $env = strtolower($env);

        return [$root, $app, $appConfig, $config, $debug, $env];
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
}




