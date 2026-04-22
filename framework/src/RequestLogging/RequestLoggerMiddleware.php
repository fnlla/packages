<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\RequestLogging;

use Fnlla\Contracts\Log\LoggerInterface;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;
use Fnlla\Runtime\Profiler;
use Fnlla\Runtime\RequestContext;
use Throwable;

final class RequestLoggerMiddleware implements MiddlewareInterface
{
    public function __construct(private ?LoggerInterface $logger = null)
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
        $start = microtime(true);
        $response = $next($request);
        if (!$response instanceof Response) {
            $response = Response::html((string) $response);
        }

        $duration = (microtime(true) - $start) * 1000;
        $memory = memory_get_peak_usage(true);
        $context = RequestContext::current();
        $requestId = $context instanceof RequestContext ? $context->requestId() : '';
        $traceId = $context instanceof RequestContext ? $context->traceId() : '';
        $spanId = $context instanceof RequestContext ? $context->spanId() : '';
        $includeRequestId = $context instanceof RequestContext ? $context->includeRequestIdHeader() : true;
        $includeTraceId = $context instanceof RequestContext ? $context->includeTraceIdHeader() : true;
        $includeSpanId = $context instanceof RequestContext ? $context->includeSpanIdHeader() : true;
        if ($includeRequestId && $requestId !== '' && !$response->hasHeader('X-Request-Id')) {
            $response = $response->withAddedHeader('X-Request-Id', $requestId);
        }
        if ($includeTraceId && $traceId !== '' && !$response->hasHeader('X-Trace-Id')) {
            $response = $response->withAddedHeader('X-Trace-Id', $traceId);
        }
        if ($includeSpanId && $spanId !== '' && !$response->hasHeader('X-Span-Id')) {
            $response = $response->withAddedHeader('X-Span-Id', $spanId);
        }

        if ($this->logger instanceof LoggerInterface) {
            try {
                $context = [
                    'method' => $request->getMethod(),
                    'path' => $request->getUri()->getPath(),
                    'status' => $response->getStatusCode(),
                    'duration_ms' => round($duration, 2),
                    'memory_peak' => $memory,
                    'ip' => $this->resolveIp($request),
                ];
                if ($requestId !== '') {
                    $context['request_id'] = $requestId;
                }
                if ($traceId !== '') {
                    $context['trace_id'] = $traceId;
                }
                if ($spanId !== '') {
                    $context['span_id'] = $spanId;
                }

                $profiler = Profiler::current();
                if ($profiler instanceof Profiler) {
                    $context = array_merge($context, $profiler->stats());
                }

                $this->logger->info('request', $context);
            } catch (Throwable $e) {
                // Ignore logging errors.
            }
        }

        $debug = getenv('APP_DEBUG') === '1';
        if ($debug) {
            $profiler = Profiler::current();
            if ($profiler instanceof Profiler) {
                $stats = $profiler->stats();
                if (!isset($stats['request_ms'])) {
                    return $response;
                }
                $response = $response
                    ->withAddedHeader('X-Profile-Time', (string) $stats['request_ms'])
                    ->withAddedHeader('X-Profile-Db', (string) ($stats['db_ms'] ?? 0))
                    ->withAddedHeader('X-Profile-Cache-Hits', (string) ($stats['cache_hits'] ?? 0))
                    ->withAddedHeader('X-Profile-Cache-Misses', (string) ($stats['cache_misses'] ?? 0));
            }
        }

        return $response;
    }

    private function resolveIp(ServerRequestInterface $request): string
    {
        if (method_exists($request, 'clientIp')) {
            $ip = $request->clientIp();
            if (is_string($ip) && $ip !== '') {
                return $ip;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
