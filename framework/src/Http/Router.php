<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Closure;
use Fnlla\Core\Container;
use Fnlla\Authorization\Gate;
use Fnlla\Authorization\PolicyRegistry;
use Fnlla\Http\RedirectTarget;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use ReflectionFunction;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * HTTP router with route registration and middleware pipeline support.
 *
 * @api
 */
final class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $groupStack = [];
    private array $namedRoutes = [];
    private array $middlewareGroups = [];
    private array $middlewareAliases = [];

    public function __construct(private string $basePath = '', private ?Container $container = null)
    {
        $this->basePath = rtrim($this->basePath, '/');
    }

    public function get(string $pattern, callable|array|string $handler, ?string $name = null, array $middleware = [], ?string $host = null): void
    {
        $this->addRoute('GET', $pattern, $handler, $name, $middleware, $host);
    }

    public function post(string $pattern, callable|array|string $handler, ?string $name = null, array $middleware = [], ?string $host = null): void
    {
        $this->addRoute('POST', $pattern, $handler, $name, $middleware, $host);
    }

    public function patch(string $pattern, callable|array|string $handler, ?string $name = null, array $middleware = [], ?string $host = null): void
    {
        $this->addRoute('PATCH', $pattern, $handler, $name, $middleware, $host);
    }

    public function add(string $method, string $pattern, callable|array|string $handler, ?string $name = null, array $middleware = [], ?string $host = null): void
    {
        $this->addRoute($method, $pattern, $handler, $name, $middleware, $host);
    }

    public function use(callable|array|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function middlewareGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    public function middlewareAlias(string $name, callable|array|string $middleware): void
    {
        $name = $this->normalizeAliasName($name);
        if ($name === '') {
            throw new RuntimeException('Middleware alias name cannot be empty.');
        }

        $this->assertValidAliasTarget($middleware, $name);

        if (isset($this->middlewareAliases[$name])) {
            if ($this->middlewareAliases[$name] !== $middleware) {
                throw new RuntimeException('Middleware alias conflict for [' . $name . '].');
            }
            return;
        }

        $this->middlewareAliases[$name] = $middleware;
    }

    public function middlewareAliases(array $aliases): void
    {
        foreach ($aliases as $name => $middleware) {
            if (!is_string($name)) {
                throw new RuntimeException('Middleware alias keys must be strings.');
            }
            $this->middlewareAlias($name, $middleware);
        }
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $this->normalizeGroup($attributes);
        $callback($this);
        array_pop($this->groupStack);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $host = $request->getUri()->getHost();

        $methodRoutes = $this->routes[$method] ?? [];

        foreach ($methodRoutes as $route) {
            if (!$this->matchesHost($route, $host)) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? null;
            }

            $requestWithParams = $request->withParams($params);

            $handler = $this->resolveHandler($route['handler']);
            $middleware = array_merge($this->globalMiddleware, $route['middleware']);
            $pipeline = $this->buildMiddlewarePipeline($middleware, $handler);
            $result = $pipeline($requestWithParams);

            return $this->normalizeResponse($result);
        }

        $allowedMethods = $this->collectAllowedMethods($path, $host, $method);

        $fallback = function (Request $req) use ($allowedMethods, $method) {
            if ($allowedMethods !== []) {
                $allowHeader = implode(', ', $allowedMethods);
                if ($method === 'OPTIONS') {
                    return Response::text('', 204, $allowHeader !== '' ? ['Allow' => $allowHeader] : []);
                }
                $response = Response::html('405 Method Not Allowed', 405);
                if ($allowHeader !== '') {
                    $response = $response->withHeader('Allow', $allowHeader);
                }
                return $response;
            }

            return Response::html('404 Not Found', 404);
        };

        $pipeline = $this->buildMiddlewarePipeline($this->globalMiddleware, $fallback);
        $result = $pipeline($request);

        return $this->normalizeResponse($result);
    }

    private function addRoute(string $method, string $pattern, callable|array|string $handler, ?string $name, array $middleware, ?string $host): void
    {
        $pattern = $this->normalizePath($pattern);
        $pattern = $this->applyGroupPrefix($pattern);

        $middleware = array_merge($this->collectGroupMiddleware(), $middleware);
        $middleware = $this->expandMiddleware($middleware);
        $middleware = $this->normalizeRateMiddleware($middleware);
        $name = $this->applyGroupNamePrefix($name);
        $host = $this->applyGroupHost($host);

        [$regex, $params] = $this->compilePattern($pattern);
        $hostRegex = $this->compileHost($host);
        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'host' => $host,
            'host_regex' => $hostRegex,
            'params' => $params,
            'handler' => $handler,
            'name' => $name,
            'middleware' => $middleware,
        ];

        if (is_string($name) && $name !== '') {
            $this->namedRoutes[$name] = [
                'pattern' => $pattern,
                'params' => $params,
            ];
        }
    }

    private function compilePattern(string $pattern): array
    {
        $params = [];
        $regex = preg_replace_callback('/\\{([a-zA-Z_][a-zA-Z0-9_]*)(:([^}]+))?\\}/', function ($matches) use (&$params) {
            $params[] = $matches[1];
            $pattern = $matches[3] ?? '[^/]+';
            return '(?P<' . $matches[1] . '>' . $pattern . ')';
        }, $pattern);

        $regex = '#^' . $regex . '/?$#';
        return [$regex, $params];
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new RuntimeException('Route [' . $name . '] is not defined.');
        }

        $pattern = $this->namedRoutes[$name]['pattern'];
        $url = preg_replace_callback('/\\{([a-zA-Z_][a-zA-Z0-9_]*)(:[^}]+)?\\}/', function ($matches) use ($params) {
            $key = $matches[1];
            if (!array_key_exists($key, $params)) {
                throw new RuntimeException('Missing route parameter [' . $key . '].');
            }
            return rawurlencode((string) $params[$key]);
        }, $pattern);

        $base = $this->basePath !== '' ? '/' . trim($this->basePath, '/') : '';
        return $base . ($url === '/' ? '/' : $url);
    }

    public function loadRoutes(array $routes): void
    {
        $this->routes = $routes;
        $this->namedRoutes = [];

        foreach ($this->routes as $method => $items) {
            foreach ($items as $route) {
                $name = $route['name'] ?? null;
                if (is_string($name) && $name !== '') {
                    $this->namedRoutes[$name] = [
                        'pattern' => $route['pattern'],
                        'params' => $route['params'] ?? [],
                    ];
                }
            }
        }
    }

    public function dumpRoutes(): array
    {
        $issues = $this->cacheIssues();
        if ($issues !== []) {
            throw new RuntimeException('Route cache contains non-cacheable entries: ' . $issues[0]);
        }

        return $this->routes;
    }

    /**
     * @return string[]
     */
    public function cacheIssues(): array
    {
        $issues = [];
        foreach ($this->routes as $method => $items) {
            foreach ($items as $route) {
                $pattern = isset($route['pattern']) ? (string) $route['pattern'] : '';
                $label = trim($method . ' ' . $pattern);
                $label = $label !== '' ? $label : 'route';

                if (!$this->isCacheableHandler($route['handler'] ?? null)) {
                    $issues[] = $label . ' handler is not cacheable (use controller strings/arrays).';
                }
                if (!$this->isCacheableMiddleware($route['middleware'] ?? [])) {
                    $issues[] = $label . ' middleware is not cacheable (use middleware class names).';
                }
            }
        }

        return $issues;
    }

    public function isValidRoutesCache(array $routes, ?string &$error = null): bool
    {
        foreach ($routes as $method => $items) {
            if (!is_string($method)) {
                $error = 'Route method keys must be strings.';
                return false;
            }
            if (!is_array($items)) {
                $error = 'Route list for [' . $method . '] must be an array.';
                return false;
            }
            foreach ($items as $route) {
                if (!is_array($route)) {
                    $error = 'Route entry must be an array.';
                    return false;
                }
                if (!isset($route['pattern'], $route['regex'], $route['params'], $route['handler'], $route['middleware'])) {
                    $error = 'Route entry missing required keys.';
                    return false;
                }
                if (!is_string($route['pattern']) || !is_string($route['regex']) || !is_array($route['params'])) {
                    $error = 'Route entry has invalid pattern/regex/params.';
                    return false;
                }
                if (!$this->isCacheableHandler($route['handler'])) {
                    $error = 'Route handler is not cacheable.';
                    return false;
                }
                if (!is_array($route['middleware']) || !$this->isCacheableMiddleware($route['middleware'])) {
                    $error = 'Route middleware is not cacheable.';
                    return false;
                }
                if (isset($route['host']) && $route['host'] !== null && !is_string($route['host'])) {
                    $error = 'Route host must be a string or null.';
                    return false;
                }
                if (isset($route['host_regex']) && $route['host_regex'] !== null && !is_string($route['host_regex'])) {
                    $error = 'Route host_regex must be a string or null.';
                    return false;
                }
            }
        }

        return true;
    }

    private function buildMiddlewarePipeline(array $middleware, callable $handler): callable
    {
        $next = function (Request $request) use ($handler) {
            return $this->invokeHandler($handler, $request);
        };

        $middleware = $this->expandMiddleware($middleware);
        $middleware = $this->normalizeRateMiddleware($middleware);

        foreach (array_reverse($middleware) as $mw) {
            $mw = $this->resolveMiddleware($mw);
            $next = function (Request $request) use ($mw, $next) {
                if ($mw instanceof MiddlewareInterface) {
                    $handler = new RequestHandler($next);
                    return $mw->process($request, $handler);
                }
                return $mw($request, $next);
            };
        }

        return $next;
    }

    private function normalizeGroup(array $attributes): array
    {
        $prefix = '';
        if (isset($attributes['prefix'])) {
            $prefix = (string) $attributes['prefix'];
        }

        $host = null;
        if (isset($attributes['host'])) {
            $host = (string) $attributes['host'];
        }

        $middleware = [];
        if (isset($attributes['middleware'])) {
            $middleware = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
        }
        $middleware = $this->expandMiddleware($middleware);

        $namePrefix = '';
        if (isset($attributes['name'])) {
            $namePrefix = (string) $attributes['name'];
        } elseif (isset($attributes['as'])) {
            $namePrefix = (string) $attributes['as'];
        }
        if ($namePrefix !== '' && !str_ends_with($namePrefix, '.')) {
            $namePrefix .= '.';
        }

        return [
            'prefix' => $prefix,
            'middleware' => $middleware,
            'name_prefix' => $namePrefix,
            'host' => $host,
        ];
    }

    private function collectGroupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }
        return $middleware;
    }

    private function applyGroupPrefix(string $pattern): string
    {
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix = $this->joinPaths($prefix, $group['prefix']);
        }

        return $this->joinPaths($prefix, $pattern);
    }

    private function applyGroupNamePrefix(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $prefix = '';
        foreach ($this->groupStack as $group) {
            $prefix .= $group['name_prefix'];
        }
        return $prefix . $name;
    }

    private function applyGroupHost(?string $host): ?string
    {
        $groupHost = null;
        foreach ($this->groupStack as $group) {
            if (isset($group['host']) && $group['host'] !== null && $group['host'] !== '') {
                $groupHost = (string) $group['host'];
            }
        }
        if ($host !== null && $host !== '') {
            return $host;
        }
        return $groupHost;
    }

    private function expandMiddleware(array $middleware): array
    {
        return $this->expandMiddlewareInternal($middleware, []);
    }

    private function expandMiddlewareInternal(array $middleware, array $seenAliases): array
    {
        $expanded = [];
        foreach ($middleware as $item) {
            if (is_string($item) && isset($this->middlewareGroups[$item])) {
                $expanded = array_merge($expanded, $this->expandMiddlewareInternal($this->middlewareGroups[$item], $seenAliases));
                continue;
            }
            if (is_string($item) && isset($this->middlewareAliases[$item])) {
                if (in_array($item, $seenAliases, true)) {
                    throw new RuntimeException('Middleware alias cycle detected for [' . $item . '].');
                }
                $target = $this->middlewareAliases[$item];
                $nextSeen = array_merge($seenAliases, [$item]);
                if (is_array($target)) {
                    $expanded = array_merge($expanded, $this->expandMiddlewareInternal($target, $nextSeen));
                } else {
                    $expanded = array_merge($expanded, $this->expandMiddlewareInternal([$target], $nextSeen));
                }
                continue;
            }
            $expanded[] = $item;
        }
        return $expanded;
    }

    private function normalizeRateMiddleware(array $middleware): array
    {
        $lastRate = null;
        $result = [];

        foreach ($middleware as $item) {
            if (is_string($item) && str_starts_with($item, 'rate:')) {
                $lastRate = $item;
                continue;
            }
            $result[] = $item;
        }

        if ($lastRate === null) {
            return $result;
        }

        $spec = strtolower(trim(substr($lastRate, 5)));
        if ($spec === '' || $spec === 'off' || $spec === 'none' || $spec === '0') {
            return $result;
        }

        $result[] = $lastRate;
        return $result;
    }

    private function joinPaths(string $left, string $right): string
    {
        $left = trim($left, '/');
        $right = trim($right, '/');
        $combined = $left === '' ? $right : ($right === '' ? $left : $left . '/' . $right);
        return $combined === '' ? '/' : '/' . $combined;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function resolveMiddleware(mixed $mw): mixed
    {
        if (is_string($mw) && str_starts_with($mw, 'can:')) {
            return $this->buildCanMiddleware($mw);
        }

        if (is_string($mw) && str_starts_with($mw, 'rate:')) {
            return $this->buildRateMiddleware($mw);
        }

        if (is_string($mw) && class_exists($mw)) {
            $instance = $this->resolveFromContainer($mw);
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
            if (is_callable($instance)) {
                return $instance;
            }
            $instance = new $mw();
            if ($instance instanceof MiddlewareInterface) {
                return $instance;
            }
            if (is_callable($instance)) {
                return $instance;
            }
        }

        if ($mw instanceof MiddlewareInterface) {
            return $mw;
        }

        if (is_callable($mw)) {
            return $mw;
        }

        throw new RuntimeException('Invalid middleware.');
    }

    private function buildCanMiddleware(string $spec): callable
    {
        $definition = trim(substr($spec, 4));
        $parts = array_map('trim', explode(',', $definition, 2));
        $ability = $parts[0] ?? '';
        $targetSpec = $parts[1] ?? '';

        return function (Request $request, callable $next) use ($ability, $targetSpec): ResponseInterface {
            if ($ability === '') {
                return $this->denyAuthorization($request);
            }

            $gate = $this->resolveGate();
            if (!$gate instanceof Gate) {
                return $this->denyAuthorization($request);
            }

            $target = null;
            if ($targetSpec !== '' && $request instanceof Request) {
                $target = $request->getAttribute($targetSpec);
            }
            if ($target === null && $targetSpec !== '') {
                if (class_exists($targetSpec)) {
                    $target = $targetSpec;
                } else {
                    $target = $targetSpec;
                }
            }

            if (!$gate->allows($ability, $target, $request)) {
                return $this->denyAuthorization($request);
            }
            return $next($request);
        };
    }

    private function denyAuthorization(Request $request): Response
    {
        if ($request->wantsJson()) {
            throw new \Fnlla\Authorization\AuthorizationException('Forbidden', 403);
        }

        $redirectTo = RedirectTarget::fromReferer($request, '/');
        return Response::redirect($redirectTo, 302);
    }

    private function buildRateMiddleware(string $spec): callable
    {
        $definition = trim(substr($spec, 5));
        $parts = array_map('trim', explode(',', $definition));
        $max = (int) ($parts[0] ?? 60);
        $minutes = (int) ($parts[1] ?? 1);
        $keySpec = (string) ($parts[2] ?? 'ip');

        $max = $max > 0 ? $max : 60;
        $minutes = $minutes > 0 ? $minutes : 1;
        $decay = $minutes * 60;

        return function (Request $request, callable $next) use ($max, $decay, $keySpec): ResponseInterface {
            if (!class_exists(\Fnlla\RateLimit\RateLimiter::class)) {
                throw new RuntimeException('Rate limiter is not installed.');
            }

            $limiter = $this->resolveRateLimiter();
            if (!$limiter instanceof \Fnlla\RateLimit\RateLimiter) {
                throw new RuntimeException('Rate limiter service is not available.');
            }

            $middleware = new \Fnlla\RateLimit\RateLimitMiddleware($limiter, $max, $decay, $keySpec);
            return $middleware($request, $next);
        };
    }

    private function resolveGate(): ?Gate
    {
        if ($this->container instanceof Container && $this->container->has(Gate::class)) {
            $gate = $this->container->make(Gate::class);
            return $gate instanceof Gate ? $gate : null;
        }

        $container = $this->container ?? new Container();
        return new Gate($container, new PolicyRegistry());
    }

    private function resolveRateLimiter(): ?\Fnlla\RateLimit\RateLimiter
    {
        if ($this->container instanceof Container && $this->container->has(\Fnlla\RateLimit\RateLimiter::class)) {
            $limiter = $this->container->make(\Fnlla\RateLimit\RateLimiter::class);
            return $limiter instanceof \Fnlla\RateLimit\RateLimiter ? $limiter : null;
        }

        return null;
    }

    private function resolveHandler(mixed $handler): callable
    {
        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $methodName] = $handler;
            $controller = $this->resolveClass($controllerClass);
            if (!method_exists($controller, (string) $methodName)) {
                throw new RuntimeException('Controller method not found: ' . (string) $methodName);
            }
            return [$controller, (string) $methodName];
        }

        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                throw new RuntimeException('Legacy route handler syntax "Class@method" is not supported in 3.x. Use [ClassName::class, \'method\'].');
            }

            if (class_exists($handler)) {
                $controller = $this->resolveClass($handler);
                if (is_callable($controller)) {
                    return $controller;
                }
                throw new RuntimeException('Controller is not callable: ' . $handler);
            }

            if (function_exists($handler)) {
                throw new RuntimeException('Global function route handlers are not supported in 3.x. Use a closure or controller class.');
            }
        }

        throw new RuntimeException('Invalid route handler.');
    }

    private function invokeHandler(callable $handler, Request $request): mixed
    {
        if ($this->container !== null) {
            $overrides = $request->getAttributes();
            $overrides['request'] = $request;
            return $this->container->call($handler, $overrides);
        }

        return $this->invokeCallableWithoutContainer($handler, $request);
    }

    private function invokeCallableWithoutContainer(callable $handler, Request $request): mixed
    {
        $reflection = is_array($handler)
            ? new ReflectionMethod($handler[0], $handler[1])
            : new ReflectionFunction(Closure::fromCallable($handler));

        if ($reflection->getNumberOfParameters() === 0) {
            return call_user_func($handler);
        }

        return call_user_func($handler, $request);
    }

    private function resolveClass(mixed $class): object
    {
        if (is_object($class)) {
            return $class;
        }

        $className = (string) $class;
        if ($className === '') {
            throw new RuntimeException('Invalid controller class.');
        }

        $instance = $this->resolveFromContainer($className);
        if (is_object($instance)) {
            return $instance;
        }

        if (!class_exists($className)) {
            throw new RuntimeException('Controller class not found: ' . $className);
        }

        return new $className();
    }

    private function resolveFromContainer(string $className): mixed
    {
        if ($this->container === null) {
            return null;
        }

        try {
            return $this->container->make($className);
        } catch (Throwable $e) {
            return null;
        }
    }

    private function isCacheableHandler(mixed $handler): bool
    {
        if (is_string($handler)) {
            return true;
        }
        if (is_array($handler) && count($handler) === 2) {
            return is_string($handler[0]) && is_string($handler[1]);
        }
        return false;
    }

    private function isCacheableMiddleware(array $middleware): bool
    {
        foreach ($middleware as $mw) {
            if (!is_string($mw)) {
                return false;
            }
        }
        return true;
    }

    private function compileHost(?string $host): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        $regex = preg_replace_callback('/\\{([a-zA-Z_][a-zA-Z0-9_]*)(:([^}]+))?\\}/', function ($matches) {
            $pattern = $matches[3] ?? '[^\\.]+';
            return '(' . $pattern . ')';
        }, $host);

        return '#^' . $regex . '$#';
    }

    private function matchesHost(array $route, string $host): bool
    {
        $routeHost = $route['host'] ?? null;
        if ($routeHost === null || $routeHost === '') {
            return true;
        }
        $regex = $route['host_regex'] ?? null;
        if (is_string($regex) && $regex !== '') {
            return preg_match($regex, $host) === 1;
        }
        return $host === $routeHost;
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof ResponseInterface) {
            $response = new Response(
                $result->getStatusCode(),
                $result->getHeaders(),
                $result->getBody(),
                $result->getReasonPhrase()
            );
            return $response->withProtocolVersion($result->getProtocolVersion());
        }

        return Response::html((string) $result);
    }

    private function collectAllowedMethods(string $path, string $host, string $currentMethod): array
    {
        $allowed = [];

        foreach ($this->routes as $routeMethod => $routesByMethod) {
            if ($routeMethod === $currentMethod) {
                continue;
            }
            foreach ($routesByMethod as $route) {
                if (!$this->matchesHost($route, $host)) {
                    continue;
                }
                if (preg_match($route['regex'], $path) !== 1) {
                    continue;
                }
                $allowed[] = $routeMethod;
                break;
            }
        }

        $allowed = array_values(array_unique($allowed));
        sort($allowed);
        return $allowed;
    }

    private function normalizeAliasName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        if (str_contains($name, ':')) {
            throw new RuntimeException('Middleware alias name cannot contain ":".');
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9._-]*$/', $name)) {
            throw new RuntimeException('Invalid middleware alias name: ' . $name);
        }
        return $name;
    }

    private function assertValidAliasTarget(mixed $middleware, string $name): void
    {
        if (is_string($middleware)) {
            if (trim($middleware) === '') {
                throw new RuntimeException('Middleware alias [' . $name . '] must not be empty.');
            }
            return;
        }
        if (is_callable($middleware)) {
            return;
        }
        if (is_array($middleware)) {
            if ($middleware === []) {
                throw new RuntimeException('Middleware alias [' . $name . '] must not be empty.');
            }
            foreach ($middleware as $item) {
                $this->assertValidAliasTarget($item, $name);
            }
            return;
        }

        throw new RuntimeException('Invalid middleware alias target for [' . $name . '].');
    }
}





