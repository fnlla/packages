<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Csrf;

use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class CsrfMiddleware implements MiddlewareInterface
{
    private array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private CsrfTokenManager $tokens)
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
        $enabled = getenv('CSRF_ENABLED');
        if ($enabled !== false) {
            $flag = strtolower(trim((string) $enabled));
            if ($flag === '' || $flag === '0' || $flag === 'false' || $flag === 'off') {
                return $next($request);
            }
        }

        if (in_array($request->getMethod(), $this->safeMethods, true)) {
            return $next($request);
        }

        $parsed = $request->getParsedBody();
        $bodyToken = null;
        if (is_array($parsed) && array_key_exists('_token', $parsed)) {
            $bodyToken = $parsed['_token'];
        }
        $token = is_string($bodyToken) ? $bodyToken : '';
        if ($token === '') {
            $token = $request->getHeaderLine('X-CSRF-Token');
        }
        if ($token === '') {
            $token = $request->getHeaderLine('X-XSRF-TOKEN');
        }

        if (!is_string($token) || $token === '' || !$this->tokens->validate($token)) {
            if ($request instanceof Request && $request->wantsJson()) {
                return Response::json(['message' => 'CSRF token mismatch.'], 419);
            }
            return Response::html('CSRF token mismatch.', 419);
        }

        return $next($request);
    }
}
