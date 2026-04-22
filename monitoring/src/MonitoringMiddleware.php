<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Monitoring;

use Fnlla\Debugbar\DebugbarCollector;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\MiddlewareInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

final class MonitoringMiddleware implements MiddlewareInterface
{
    public function __construct(private MonitoringManager $monitoring)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);
        $response = $handler->handle($request);
        $duration = (microtime(true) - $start) * 1000;
        $status = $response->getStatusCode();
        $context = $this->requestContext($request, $response);
        $debug = $this->debugStats();
        if ($debug !== null) {
            $context['debug'] = $debug;
        }

        $this->monitoring->recordRequest($status, $duration, $context);
        if ($debug !== null) {
            $this->monitoring->recordDebugStats($debug);
        }
        $this->monitoring->recordProfilerStats();
        return $response;
    }

    public function __invoke(\Fnlla\Http\Request $request, callable $next): ResponseInterface
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;
        $status = $response->getStatusCode();
        $context = $this->requestContext($request, $response);
        $debug = $this->debugStats();
        if ($debug !== null) {
            $context['debug'] = $debug;
        }

        $this->monitoring->recordRequest($status, $duration, $context);
        if ($debug !== null) {
            $this->monitoring->recordDebugStats($debug);
        }
        $this->monitoring->recordProfilerStats();
        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function requestContext(ServerRequestInterface $request, ResponseInterface $response): array
    {
        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();
        if ($query !== '') {
            $path .= '?' . $query;
        }

        return [
            'method' => strtoupper($request->getMethod()),
            'path' => $path,
            'request_id' => $response->getHeaderLine('X-Request-Id'),
            'trace_id' => $response->getHeaderLine('X-Trace-Id'),
            'span_id' => $response->getHeaderLine('X-Span-Id'),
        ];
    }

    /**
     * @return array<string, int|float>|null
     */
    private function debugStats(): ?array
    {
        if (!class_exists(DebugbarCollector::class)) {
            return null;
        }

        return [
            'queries' => count(DebugbarCollector::queries()),
            'messages' => count(DebugbarCollector::messages()),
            'errors' => count(DebugbarCollector::errors()),
            'slow_queries' => $this->toInt(DebugbarCollector::queries(), $this->slowQueryThreshold()),
            'request_ms' => DebugbarCollector::requestTimeMs(),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $queries
     */
    private function toInt(array $queries, float $thresholdMs): int
    {
        $slow = 0;
        foreach ($queries as $query) {
            if ((float) ($query['time_ms'] ?? 0.0) >= $thresholdMs) {
                $slow++;
            }
        }
        return $slow;
    }

    private function slowQueryThreshold(): float
    {
        $value = getenv('DEBUGBAR_SLOW_QUERY_MS');
        if ($value === false || $value === '') {
            return 25.0;
        }
        if (!is_numeric($value)) {
            return 25.0;
        }
        return max(0.1, (float) $value);
    }
}
