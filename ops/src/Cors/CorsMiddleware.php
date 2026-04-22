<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cors;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigRepository $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->handle($request, fn ($req): ResponseInterface => $handler->handle($req));
    }

    public function __invoke(Request $request, callable $next): ResponseInterface
    {
        return $this->handle($request, $next);
    }

    private function handle(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $config = $this->config->get('cors', []);
        if (!is_array($config)) {
            $config = [];
        }

        $enabled = $config['enabled'] ?? true;
        if ($enabled === false || $enabled === 0 || $enabled === '0') {
            return $next($request);
        }

        $origin = trim((string) $request->getHeaderLine('Origin'));
        $headers = $this->resolveHeaders($config, $origin, $request);
        if ($headers === []) {
            return $next($request);
        }

        $isPreflight = strtoupper($request->getMethod()) === 'OPTIONS'
            && $request->getHeaderLine('Access-Control-Request-Method') !== '';

        if ($isPreflight) {
            $headers['Vary'] = $this->mergeVary($headers['Vary'] ?? '', [
                'Origin',
                'Access-Control-Request-Method',
                'Access-Control-Request-Headers',
            ]);
            return Response::text('', 204, $headers);
        }

        $response = $next($request);
        return $this->applyHeaders($response, $headers);
    }

    private function resolveHeaders(array $config, string $origin, ServerRequestInterface $request): array
    {
        $allowedOrigins = $this->normalizeAllowedOrigins($config['allowed_origins'] ?? ['*']);
        if ($allowedOrigins === []) {
            return [];
        }

        $allowCredentials = (bool) ($config['allow_credentials'] ?? false);
        if ($allowCredentials && in_array('*', $allowedOrigins, true)) {
            $allowedOrigins = array_values(array_filter($allowedOrigins, static fn (string $origin): bool => $origin !== '*'));
            if ($allowedOrigins === []) {
                error_log('CORS misconfiguration: wildcard origins cannot be combined with allow_credentials=true.');
                return [];
            }
        }

        $normalizedOrigin = $this->normalizeOrigin($origin);
        if ($origin !== '' && $normalizedOrigin === '') {
            return [];
        }

        $allowOrigin = null;
        if ($normalizedOrigin !== '') {
            if (in_array('*', $allowedOrigins, true)) {
                $allowOrigin = '*';
            } elseif (in_array($normalizedOrigin, $allowedOrigins, true)) {
                $allowOrigin = $normalizedOrigin;
            }
        }

        if ($allowOrigin === null) {
            return [];
        }

        $allowedMethods = $this->normalizeMethods($config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);
        if ($allowedMethods === []) {
            $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        }

        $allowedHeadersConfig = $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $allowedHeaders = $this->normalizeHeaderList($allowedHeadersConfig);

        $requestHeaders = trim((string) $request->getHeaderLine('Access-Control-Request-Headers'));
        if ($requestHeaders !== '' && ($config['allowed_headers'] ?? null) === null) {
            $allowedHeaders = $this->normalizeHeaderList(explode(',', $requestHeaders));
        }
        if ($allowedHeaders === []) {
            $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With'];
        }

        $exposedHeaders = $this->normalizeHeaderList($config['exposed_headers'] ?? []);

        $maxAge = $this->normalizeMaxAge($config['max_age'] ?? null);

        $headers = [
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => implode(', ', array_map('strtoupper', $allowedMethods)),
            'Access-Control-Allow-Headers' => implode(', ', $allowedHeaders),
        ];

        if ($allowCredentials) {
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }
        if ($exposedHeaders !== []) {
            $headers['Access-Control-Expose-Headers'] = implode(', ', $exposedHeaders);
        }
        if ($maxAge !== null) {
            $headers['Access-Control-Max-Age'] = (string) $maxAge;
        }
        if ($allowOrigin !== '*') {
            $headers['Vary'] = $this->mergeVary('', ['Origin']);
        }

        return $headers;
    }

    private function applyHeaders(ResponseInterface $response, array $headers): ResponseInterface
    {
        foreach ($headers as $name => $value) {
            $updated = $response->withHeader($name, (string) $value);
            if (!$updated instanceof ResponseInterface) {
                return $response;
            }
            $response = $updated;
        }

        return $response;
    }

    /**
     * @param mixed $origins
     * @return array<int, string>
     */
    private function normalizeAllowedOrigins(mixed $origins): array
    {
        if (!is_array($origins)) {
            $origins = [$origins];
        }

        $normalized = [];
        foreach ($origins as $origin) {
            if (!is_scalar($origin)) {
                continue;
            }

            $value = trim((string) $origin);
            if ($value === '') {
                continue;
            }

            if ($value === '*') {
                $normalized[] = '*';
                continue;
            }

            $normalizedOrigin = $this->normalizeOrigin($value);
            if ($normalizedOrigin !== '') {
                $normalized[] = $normalizedOrigin;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);
        if ($origin === '') {
            return '';
        }

        if (strcasecmp($origin, 'null') === 0) {
            return 'null';
        }

        $parts = parse_url($origin);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === '' || $host === '' || !in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            return '';
        }

        $normalized = $scheme . '://' . $host;
        $port = $parts['port'] ?? null;
        if (is_int($port) && !$this->isDefaultPort($scheme, $port)) {
            $normalized .= ':' . $port;
        }

        return $normalized;
    }

    /**
     * @param mixed $methods
     * @return array<int, string>
     */
    private function normalizeMethods(mixed $methods): array
    {
        if (!is_array($methods)) {
            $methods = [$methods];
        }

        $normalized = [];
        foreach ($methods as $method) {
            if (!is_scalar($method)) {
                continue;
            }

            $value = strtoupper(trim((string) $method));
            if ($value === '' || preg_match('/^[A-Z*]+$/', $value) !== 1) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param mixed $headers
     * @return array<int, string>
     */
    private function normalizeHeaderList(mixed $headers): array
    {
        if ($headers === null) {
            return [];
        }

        if (is_string($headers)) {
            $headers = explode(',', $headers);
        } elseif (!is_array($headers)) {
            $headers = [$headers];
        }

        $normalized = [];
        foreach ($headers as $header) {
            if (!is_scalar($header)) {
                continue;
            }

            $value = trim((string) $header);
            if ($value === '' || ($value !== '*' && preg_match('/^[A-Za-z0-9-]+$/', $value) !== 1)) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeMaxAge(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_string($value) && is_numeric($value)) {
            $parsed = (int) $value;
            return $parsed >= 0 ? $parsed : null;
        }

        return null;
    }

    /**
     * @param array<int, string> $values
     */
    private function mergeVary(string $current, array $values): string
    {
        $items = [];
        if ($current !== '') {
            $items = array_map('trim', explode(',', $current));
        }

        foreach ($values as $value) {
            $token = trim($value);
            if ($token !== '') {
                $items[] = $token;
            }
        }

        $unique = [];
        foreach ($items as $item) {
            $key = strtolower($item);
            if ($item === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $item;
        }

        return implode(', ', array_values($unique));
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
    }
}
