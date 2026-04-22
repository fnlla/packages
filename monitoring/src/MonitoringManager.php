<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Monitoring;

use Fnlla\Cache\CacheManager;
use Fnlla\Runtime\Profiler;

final class MonitoringManager
{
    private string $prefix;
    private int $ttl;
    private int $recentLimit;

    public function __construct(private CacheManager $cache, array $config = [])
    {
        $this->prefix = (string) ($config['prefix'] ?? 'monitoring');
        $this->ttl = (int) ($config['cache_ttl'] ?? 3600);
        $this->recentLimit = max(1, (int) ($config['recent_limit'] ?? 30));
    }

    public function recordRequest(int $status, float $durationMs, array $context = []): void
    {
        $this->increment('requests.total');
        $this->increment('requests.status.' . $status);
        $this->rememberStatusCode($status);
        $this->increment('request_ms.sum', $durationMs);
        $currentMax = (float) $this->get('request_ms.max', 0.0);
        if ($durationMs > $currentMax) {
            $this->set('request_ms.max', $durationMs);
        }

        $this->set('last.request.method', strtoupper((string) ($context['method'] ?? 'GET')));
        $this->set('last.request.path', (string) ($context['path'] ?? '/'));
        $this->set('last.request.status', $status);
        $this->set('last.request.duration_ms', round($durationMs, 2));
        $this->set('last.request.at', gmdate('c'));
        if (isset($context['request_id'])) {
            $this->set('last.request.request_id', (string) $context['request_id']);
        }
        if (isset($context['trace_id'])) {
            $this->set('last.request.trace_id', (string) $context['trace_id']);
        }
        if (isset($context['span_id'])) {
            $this->set('last.request.span_id', (string) $context['span_id']);
        }

        $trace = [
            'at' => gmdate('c'),
            'method' => strtoupper((string) ($context['method'] ?? 'GET')),
            'path' => (string) ($context['path'] ?? '/'),
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
        ];

        foreach (['request_id', 'trace_id', 'span_id'] as $idKey) {
            if (isset($context[$idKey]) && is_string($context[$idKey]) && $context[$idKey] !== '') {
                $trace[$idKey] = $context[$idKey];
            }
        }

        if (isset($context['debug']) && is_array($context['debug'])) {
            $debug = $context['debug'];
            $trace['debug'] = [
                'queries' => (int) ($debug['queries'] ?? 0),
                'messages' => (int) ($debug['messages'] ?? 0),
                'errors' => (int) ($debug['errors'] ?? 0),
                'slow_queries' => (int) ($debug['slow_queries'] ?? 0),
                'request_ms' => (float) ($debug['request_ms'] ?? 0.0),
                'memory_mb' => (float) ($debug['memory_mb'] ?? 0.0),
            ];
        }

        $this->appendRecentTrace($trace);
    }

    public function recordDebugStats(array $stats): void
    {
        $queries = max(0, (int) ($stats['queries'] ?? 0));
        $messages = max(0, (int) ($stats['messages'] ?? 0));
        $errors = max(0, (int) ($stats['errors'] ?? 0));
        $slowQueries = max(0, (int) ($stats['slow_queries'] ?? 0));
        $requestMs = max(0.0, (float) ($stats['request_ms'] ?? 0.0));
        $memoryMb = max(0.0, (float) ($stats['memory_mb'] ?? 0.0));

        $this->increment('debug.queries.total', $queries);
        $this->increment('debug.messages.total', $messages);
        $this->increment('debug.errors.total', $errors);
        $this->increment('debug.slow_queries.total', $slowQueries);
        $this->increment('debug.request_ms.sum', $requestMs);

        $currentRequestMax = (float) $this->get('debug.request_ms.max', 0.0);
        if ($requestMs > $currentRequestMax) {
            $this->set('debug.request_ms.max', $requestMs);
        }

        $currentMemoryMax = (float) $this->get('debug.memory_mb.max', 0.0);
        if ($memoryMb > $currentMemoryMax) {
            $this->set('debug.memory_mb.max', $memoryMb);
        }

        $this->set('last.debug.queries', $queries);
        $this->set('last.debug.messages', $messages);
        $this->set('last.debug.errors', $errors);
        $this->set('last.debug.slow_queries', $slowQueries);
        $this->set('last.debug.request_ms', round($requestMs, 2));
        $this->set('last.debug.memory_mb', round($memoryMb, 2));
    }

    public function recordProfilerStats(): void
    {
        $profiler = Profiler::current();
        if (!$profiler instanceof Profiler) {
            return;
        }
        $stats = $profiler->stats();
        $this->set('last.request_ms', (float) ($stats['request_ms'] ?? 0.0));
        $this->set('last.db_ms', (float) ($stats['db_ms'] ?? 0.0));
        $this->set('last.db_queries', (int) ($stats['db_queries'] ?? 0));
        $this->set('last.cache_hits', (int) ($stats['cache_hits'] ?? 0));
        $this->set('last.cache_misses', (int) ($stats['cache_misses'] ?? 0));
    }

    public function metrics(): array
    {
        $statuses = $this->collectStatuses();

        return [
            'requests_total' => (int) $this->get('requests.total', 0),
            'requests_status' => $statuses,
            'request_ms_sum' => (float) $this->get('request_ms.sum', 0.0),
            'request_ms_max' => (float) $this->get('request_ms.max', 0.0),
            'debug' => [
                'queries_total' => (int) $this->get('debug.queries.total', 0),
                'messages_total' => (int) $this->get('debug.messages.total', 0),
                'errors_total' => (int) $this->get('debug.errors.total', 0),
                'slow_queries_total' => (int) $this->get('debug.slow_queries.total', 0),
                'request_ms_sum' => (float) $this->get('debug.request_ms.sum', 0.0),
                'request_ms_max' => (float) $this->get('debug.request_ms.max', 0.0),
                'memory_mb_max' => (float) $this->get('debug.memory_mb.max', 0.0),
            ],
            'last' => [
                'request_ms' => (float) $this->get('last.request_ms', 0.0),
                'db_ms' => (float) $this->get('last.db_ms', 0.0),
                'db_queries' => (int) $this->get('last.db_queries', 0),
                'cache_hits' => (int) $this->get('last.cache_hits', 0),
                'cache_misses' => (int) $this->get('last.cache_misses', 0),
                'request' => [
                    'method' => (string) $this->get('last.request.method', ''),
                    'path' => (string) $this->get('last.request.path', ''),
                    'status' => (int) $this->get('last.request.status', 0),
                    'duration_ms' => (float) $this->get('last.request.duration_ms', 0.0),
                    'request_id' => (string) $this->get('last.request.request_id', ''),
                    'trace_id' => (string) $this->get('last.request.trace_id', ''),
                    'span_id' => (string) $this->get('last.request.span_id', ''),
                    'at' => (string) $this->get('last.request.at', ''),
                ],
                'debug' => [
                    'queries' => (int) $this->get('last.debug.queries', 0),
                    'messages' => (int) $this->get('last.debug.messages', 0),
                    'errors' => (int) $this->get('last.debug.errors', 0),
                    'slow_queries' => (int) $this->get('last.debug.slow_queries', 0),
                    'request_ms' => (float) $this->get('last.debug.request_ms', 0.0),
                    'memory_mb' => (float) $this->get('last.debug.memory_mb', 0.0),
                ],
            ],
            'recent_traces' => $this->recentTraces(),
        ];
    }

    private function key(string $name): string
    {
        return $this->prefix . '.' . $name;
    }

    private function get(string $name, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($name), $default);
    }

    private function set(string $name, mixed $value): void
    {
        $this->cache->set($this->key($name), $value, $this->ttl);
    }

    private function increment(string $name, float $by = 1.0): void
    {
        $current = (float) $this->get($name, 0.0);
        $this->set($name, $current + $by);
    }

    private function rememberStatusCode(int $status): void
    {
        $codes = $this->get('requests.status.codes', []);
        if (!is_array($codes)) {
            $codes = [];
        }
        $code = (string) $status;
        if (!in_array($code, $codes, true)) {
            $codes[] = $code;
            sort($codes);
            $this->set('requests.status.codes', $codes);
        }
    }

    /**
     * @return array<string, int>
     */
    private function collectStatuses(): array
    {
        $codes = $this->get('requests.status.codes', []);
        if (!is_array($codes)) {
            return [];
        }
        $statuses = [];
        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }
            $statuses[$code] = (int) $this->get('requests.status.' . $code, 0);
        }
        ksort($statuses);
        return $statuses;
    }

    /**
     * @param array<string, mixed> $trace
     */
    private function appendRecentTrace(array $trace): void
    {
        $items = $this->recentTraces();
        array_unshift($items, $trace);
        if (count($items) > $this->recentLimit) {
            $items = array_slice($items, 0, $this->recentLimit);
        }
        $this->set('recent.traces', $items);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentTraces(): array
    {
        $items = $this->get('recent.traces', []);
        if (!is_array($items)) {
            return [];
        }
        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }
}
