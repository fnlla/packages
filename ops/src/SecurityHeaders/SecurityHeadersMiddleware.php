<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\SecurityHeaders;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Runtime\RequestContext;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigRepository $config, private RequestContext $context)
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
        $response = $next($request);
        if ($response instanceof Response) {
            // keep as-is
        } elseif ($response instanceof ResponseInterface) {
            $response = new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                \Fnlla\Http\Stream::fromString((string) $response->getBody()),
                $response->getReasonPhrase()
            );
        } else {
            $response = Response::html((string) $response);
        }

        $config = $this->config->get('security', []);
        if (!is_array($config)) {
            $config = [];
        }

        $headers = $this->defaultHeaders();
        $customHeaders = $config['headers'] ?? [];
        if (is_array($customHeaders)) {
            foreach ($customHeaders as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $setHeaders = [];
        foreach ($headers as $name => $value) {
            if ($value === null) {
                continue;
            }
            if (!$response->hasHeader($name)) {
                $setHeaders[$name] = (string) $value;
            }
        }

        $csp = $config['csp'] ?? null;
        $csp = is_string($csp) ? trim($csp) : $csp;
        if (is_string($csp) && $csp !== '') {
            $reportOnly = $this->toBool($config['csp_report_only'] ?? false, false);
            $cspHeader = $reportOnly ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            if ($response->hasHeader($cspHeader)) {
                // keep existing CSP header from the response
            } else {
                $nonce = $this->context->cspNonce();
                $setHeaders[$cspHeader] = $this->resolveCspValue($csp, $nonce);
            }
        }

        if ($setHeaders === []) {
            return $response;
        }

        return $response->withHeaders($setHeaders);
    }

    private function defaultHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
    }

    private function resolveCspValue(string $policy, string $nonce): string
    {
        if (str_contains($policy, '%s')) {
            return sprintf($policy, $nonce);
        }

        $policy = str_replace(['{nonce}', '{{nonce}}'], $nonce, $policy);
        return $policy;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }
        return $default;
    }
}
