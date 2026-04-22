<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Forms;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class HoneypotMiddleware implements MiddlewareInterface
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
        $config = $this->config->get('forms.honeypot', []);
        if (!is_array($config) || ($config['enabled'] ?? true) === false) {
            return $next($request);
        }

        $method = strtoupper($request->getMethod());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $field = (string) ($config['field'] ?? 'website');
        $timeField = (string) ($config['time_field'] ?? '_form_time');
        $minSeconds = (int) ($config['min_seconds'] ?? 0);

        $data = method_exists($request, 'allInput') ? $request->allInput() : [];
        if (!is_array($data)) {
            $data = [];
        }

        if ($field !== '' && isset($data[$field]) && trim((string) $data[$field]) !== '') {
            return Response::text('Invalid form submission.', 422);
        }

        if ($minSeconds > 0 && $timeField !== '' && isset($data[$timeField])) {
            $submitted = (int) $data[$timeField];
            if ($submitted > 0) {
                $elapsed = time() - $submitted;
                if ($elapsed >= 0 && $elapsed < $minSeconds) {
                    return Response::text('Invalid form submission.', 422);
                }
            }
        }

        return $next($request);
    }
}
