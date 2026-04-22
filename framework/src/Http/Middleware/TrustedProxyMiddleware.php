<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http\Middleware;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class TrustedProxyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request instanceof Request) {
            $request = $request
                ->withAttribute('client_ip', $request->clientIp())
                ->withAttribute('is_secure', $request->isSecure());
        }

        return $handler->handle($request);
    }

    public function __invoke(Request $request, callable $next): Response
    {
        $request = $request
            ->withAttribute('client_ip', $request->clientIp())
            ->withAttribute('is_secure', $request->isSecure());

        return $next($request);
    }
}






