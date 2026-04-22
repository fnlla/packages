<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\CacheStatic;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class StaticCacheMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigRepository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handle($request, fn ($req): ResponseInterface => $handler->handle($req));
    }

    public function __invoke(\Fnlla\Http\Request $request, callable $next): ResponseInterface
    {
        return $this->handle($request, $next);
    }

    private function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $config = $this->config->get('cache_static', []);
        if (!is_array($config) || ($config['enabled'] ?? false) !== true) {
            return $next($request);
        }

        $method = strtoupper($request->getMethod());
        if ($method !== 'GET') {
            return $next($request);
        }

        $uri = $request->getUri();
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';
        $exclude = $config['exclude'] ?? [];
        if (is_array($exclude)) {
            foreach ($exclude as $prefix) {
                if (is_string($prefix) && $prefix !== '' && str_starts_with($path, $prefix)) {
                    return $next($request);
                }
            }
        }

        $cacheDir = (string) ($config['path'] ?? 'storage/cache/static');
        if ($cacheDir === '') {
            return $next($request);
        }

        $cacheDir = $this->normalizePath($cacheDir);
        if (!is_dir($cacheDir) && !@mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            return $next($request);
        }

        $ignoreQuery = $config['ignore_query'] ?? [];
        $key = $this->buildKey($uri->getPath(), $uri->getQuery(), $ignoreQuery);
        $cacheFile = rtrim($cacheDir, '/\\') . '/' . $key . '.json';

        $ttl = (int) ($config['ttl'] ?? 3600);
        if ($ttl < 0) {
            $ttl = 0;
        }

        $cached = $this->loadCache($cacheFile, $ttl);
        if ($cached instanceof ResponseInterface) {
            return $cached;
        }

        $response = $next($request);
        $this->storeCache($cacheFile, $response, $ttl);
        return $response;
    }

    private function buildKey(string $path, string $query, mixed $ignoreQuery): string
    {
        $normalizedPath = $path === '' ? '/' : $path;
        $queryString = $this->normalizeQuery($query, $ignoreQuery);
        $raw = $normalizedPath . ($queryString !== '' ? '?' . $queryString : '');
        return sha1($raw);
    }

    private function normalizeQuery(string $query, mixed $ignoreQuery): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $params);
        if (!is_array($params) || $params === []) {
            return $query;
        }

        if (is_array($ignoreQuery)) {
            foreach ($ignoreQuery as $key) {
                if (is_string($key)) {
                    unset($params[$key]);
                }
            }
        }

        if ($params === []) {
            return '';
        }

        ksort($params);
        return http_build_query($params);
    }

    private function loadCache(string $cacheFile, int $ttl): ?ResponseInterface
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        $payload = json_decode((string) file_get_contents($cacheFile), true);
        if (!is_array($payload)) {
            return null;
        }

        $created = (int) ($payload['created'] ?? 0);
        if ($ttl > 0 && $created > 0 && (time() - $created) > $ttl) {
            @unlink($cacheFile);
            return null;
        }

        $status = (int) ($payload['status'] ?? 200);
        $headers = $payload['headers'] ?? [];
        $body = (string) ($payload['body'] ?? '');

        if (!is_array($headers)) {
            $headers = [];
        }

        return new Response($status, $headers, Stream::fromString($body));
    }

    private function storeCache(string $cacheFile, ResponseInterface $response, int $ttl): void
    {
        if (!$response instanceof Response) {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $cacheControl = strtolower($response->getHeaderLine('Cache-Control'));
        if (str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'private')) {
            return;
        }

        if ($response->hasHeader('Set-Cookie')) {
            return;
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return;
        }

        $body = (string) $response->getBody();
        $payload = [
            'created' => time(),
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'body' => $body,
        ];

        @file_put_contents($cacheFile, json_encode($payload));
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)) {
            return $path;
        }

        $root = ConfigRepository::resolveAppRoot();
        return rtrim($root, '/\\') . '/' . $path;
    }
}
