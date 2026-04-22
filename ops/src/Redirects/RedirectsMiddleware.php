<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Redirects;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class RedirectsMiddleware implements MiddlewareInterface
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
        $config = $this->config->get('redirects', []);
        if (!is_array($config) || ($config['enabled'] ?? true) === false) {
            return $next($request);
        }

        if (!$this->isRedirectable($request)) {
            return $next($request);
        }

        $uri = $request->getUri();
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';

        if (($config['force_https'] ?? false) && method_exists($request, 'isSecure') && !$request->isSecure()) {
            $target = (string) $uri->withScheme('https');
            return Response::redirect($target, 301);
        }

        $policy = $config['trailing_slash'] ?? 'ignore';
        if ($policy === 'remove' && $path !== '/' && str_ends_with($path, '/')) {
            $target = (string) $uri->withPath(rtrim($path, '/'));
            return Response::redirect($target, 301);
        }
        if ($policy === 'add' && $path !== '/' && !str_ends_with($path, '/')) {
            $target = (string) $uri->withPath($path . '/');
            return Response::redirect($target, 301);
        }

        $rules = $config['rules'] ?? [];
        if (is_array($rules) && $rules !== []) {
            $match = $rules[$path] ?? null;
            if ($match !== null) {
                [$target, $code] = $this->resolveRule($match);
                if ($target !== null) {
                    $targetUri = $target;
                    if (str_starts_with($target, '/')) {
                        $targetUri = (string) $uri->withPath($target)->withQuery('');
                    }
                    return Response::redirect($targetUri, $code);
                }
            }
        }

        return $next($request);
    }

    private function resolveRule(mixed $rule): array
    {
        if (is_string($rule)) {
            return [$rule, 301];
        }

        if (is_array($rule)) {
            $target = $rule['to'] ?? $rule['target'] ?? null;
            $code = (int) ($rule['code'] ?? 301);
            if (is_string($target) && $target !== '') {
                return [$target, $code >= 300 && $code < 400 ? $code : 301];
            }
        }

        return [null, 301];
    }

    private function isRedirectable(ServerRequestInterface $request): bool
    {
        $method = strtoupper($request->getMethod());
        return $method === 'GET' || $method === 'HEAD';
    }
}
