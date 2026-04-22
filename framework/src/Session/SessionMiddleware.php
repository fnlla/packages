<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Session;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(private FileSessionStore $sessions)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->startFromRequest($request);
        $response = $handler->handle($request);
        return $this->attachToResponse($response);
    }

    public function __invoke(Request $request, callable $next): ResponseInterface
    {
        $this->startFromRequest($request);
        $response = $next($request);
        return $this->attachToResponse($response);
    }

    private function startFromRequest(ServerRequestInterface $request): void
    {
        if ($request instanceof Request) {
            $this->sessions->applyRequestContext($request);
        }
        $cookies = $request->getCookieParams();
        $sessionId = $cookies[$this->sessions->cookieName()] ?? null;
        $this->sessions->start(is_string($sessionId) ? $sessionId : null);
    }

    private function attachToResponse(ResponseInterface $response): ResponseInterface
    {
        $this->sessions->save();

        if ($response instanceof Response) {
            return $response->withAddedHeader('Set-Cookie', $this->sessions->cookieHeader());
        }

        return $response;
    }
}
