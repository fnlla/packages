<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Maintenance;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class MaintenanceMiddleware implements MiddlewareInterface
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
        $config = $this->config->get('maintenance', []);
        if (!is_array($config)) {
            $config = [];
        }

        $enabled = $config['enabled'] ?? false;
        if (!$enabled) {
            return $next($request);
        }

        $secret = (string) ($config['secret'] ?? '');
        if ($secret !== '') {
            $headerSecret = $request->getHeaderLine('X-Maintenance-Secret');
            if (hash_equals($secret, $headerSecret)) {
                return $next($request);
            }
        }

        $allowedIps = $config['allowed_ips'] ?? [];
        if (!is_array($allowedIps)) {
            $allowedIps = [$allowedIps];
        }
        $clientIp = $this->clientIp($request);
        if ($clientIp !== '' && in_array($clientIp, $allowedIps, true)) {
            return $next($request);
        }

        $retryAfter = (int) ($config['retry_after'] ?? 60);
        $headers = $retryAfter > 0 ? ['Retry-After' => (string) $retryAfter] : [];

        if ($request instanceof Request && $request->wantsJson()) {
            return Response::json(['message' => 'Maintenance mode'], 503)->withHeaders($headers);
        }

        return Response::html('<h1>Maintenance</h1><p>Please try again later.</p>', 503)->withHeaders($headers);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        if ($request instanceof Request) {
            return $request->clientIp();
        }

        $server = $request->getServerParams();
        return (string) ($server['REMOTE_ADDR'] ?? '');
    }
}
