<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cookie;

use Fnlla\Http\Response;
use Fnlla\Http\Request;
use Fnlla\Support\Env;

final class CookieJar
{
    private array $queued = [];
    private ?object $request = null;

    public function queue(Cookie $cookie): void
    {
        $this->queued[] = $cookie;
    }

    public function setRequest(?object $request): void
    {
        $this->request = $request;
    }

    public function make(string $name, string $value, int $lifetime = 0, array $options = []): Cookie
    {
        $secure = array_key_exists('secure', $options)
            ? (bool) $options['secure']
            : $this->defaultSecure($options['request'] ?? $this->request);

        return new Cookie(
            $name,
            $value,
            $lifetime,
            (string) ($options['path'] ?? '/'),
            (string) ($options['domain'] ?? ''),
            $secure,
            (bool) ($options['httponly'] ?? true),
            (string) ($options['samesite'] ?? 'Lax')
        );
    }

    public function attachToResponse(Response $response): Response
    {
        if ($this->queued === []) {
            return $response;
        }

        foreach ($this->queued as $cookie) {
            $response = $response->withAddedHeader('Set-Cookie', $cookie->toHeader());
        }

        $this->queued = [];
        return $response;
    }

    private function defaultSecure(?object $request): bool
    {
        if ($request instanceof Request || (is_object($request) && method_exists($request, 'isSecure'))) {
            try {
                $secure = $request->isSecure();
                if (is_bool($secure)) {
                    return $secure;
                }
            } catch (\Throwable $e) {
                // Fall back to env if request inspection fails.
            }
        }

        $env = (string) Env::get('APP_ENV', '');
        if ($env === '') {
            return false;
        }

        return strtolower($env) === 'prod';
    }
}
