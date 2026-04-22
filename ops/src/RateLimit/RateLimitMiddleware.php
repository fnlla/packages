<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\RateLimit;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiter $limiter,
        private int $maxAttempts = 60,
        private int $decaySeconds = 60,
        private string $keySpec = 'ip'
    )
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
        $key = $this->resolveKey($request);
        $state = $this->limiter->consume($key, $this->maxAttempts, $this->decaySeconds);
        if (!$state->allowed()) {
            $headers = [
                'Retry-After' => (string) $state->retryAfter(),
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => (string) $state->remaining(),
                'X-RateLimit-Reset' => (string) $state->resetAt(),
            ];
            return Response::json(['message' => 'Too Many Requests'], 429, $headers);
        }

        $response = $next($request);
        if ($response instanceof Response) {
            return $response
                ->withAddedHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
                ->withAddedHeader('X-RateLimit-Remaining', (string) $state->remaining())
                ->withAddedHeader('X-RateLimit-Reset', (string) $state->resetAt());
        }
        return $response;
    }

    private function resolveKey(ServerRequestInterface $request): string
    {
        $path = $request->getUri()->getPath();
        $method = $request->getMethod();
        $parts = $this->parseKeySpec($this->keySpec);

        $segments = [];
        foreach ($parts as $part) {
            if ($part === 'ip') {
                $segments[] = 'ip:' . $this->resolveIp($request);
                continue;
            }
            if ($part === 'user') {
                $segments[] = 'user:' . $this->resolveUserId($request);
                continue;
            }
            if ($part === 'api') {
                $segments[] = 'api:' . $this->resolveApiKey($request);
                continue;
            }
        }

        if ($segments === []) {
            $segments[] = 'ip:' . $this->resolveIp($request);
        }

        return 'rate:' . $method . ':' . $path . ':' . implode('|', $segments);
    }

    private function parseKeySpec(string $spec): array
    {
        $spec = strtolower(trim($spec));
        if ($spec === '') {
            return ['ip'];
        }

        $parts = preg_split('/[|,+]/', $spec) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($item) => $item !== ''));
        return $parts !== [] ? $parts : ['ip'];
    }

    private function resolveIp(ServerRequestInterface $request): string
    {
        if (method_exists($request, 'clientIp')) {
            $ip = $request->clientIp();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }

    private function resolveUserId(ServerRequestInterface $request): string
    {
        if (method_exists($request, 'getAttribute')) {
            $user = $request->getAttribute('user');
            if (is_object($user)) {
                if (method_exists($user, 'getId')) {
                    return (string) $user->getId();
                }
                if (method_exists($user, 'id')) {
                    return (string) $user->id();
                }
            }

            $userId = $request->getAttribute('user_id');
            if (is_scalar($userId)) {
                return (string) $userId;
            }
        }

        return 'guest';
    }

    private function resolveApiKey(ServerRequestInterface $request): string
    {
        if (method_exists($request, 'getHeaderLine')) {
            $apiKey = $request->getHeaderLine('X-API-Key');
            if ($apiKey !== '') {
                return $apiKey;
            }

            $auth = $request->getHeaderLine('Authorization');
            if (str_starts_with(strtolower($auth), 'bearer ')) {
                return trim(substr($auth, 7));
            }
        }

        if (method_exists($request, 'getQueryParams')) {
            $query = $request->getQueryParams();
            if (is_array($query) && isset($query['api_key'])) {
                return (string) $query['api_key'];
            }
        }

        return 'none';
    }
}
