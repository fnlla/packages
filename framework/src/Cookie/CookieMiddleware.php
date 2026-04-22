<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cookie;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class CookieMiddleware implements MiddlewareInterface
{
    public function __construct(private CookieJar $jar)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->jar->setRequest($request);
        $response = $handler->handle($request);
        $this->jar->setRequest(null);
        if ($response instanceof Response) {
            return $this->jar->attachToResponse($response);
        }
        return $response;
    }

    public function __invoke(Request $request, callable $next): ResponseInterface
    {
        $this->jar->setRequest($request);
        $response = $next($request);
        $this->jar->setRequest(null);
        if ($response instanceof Response) {
            return $this->jar->attachToResponse($response);
        }
        return $response;
    }
}
