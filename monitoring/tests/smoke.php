<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Monitoring\MonitoringManager;
use Fnlla\Cache\CacheManager;

try {
    $cache = new CacheManager(['driver' => 'array']);
    $monitoring = new MonitoringManager($cache, ['cache_ttl' => 60, 'recent_limit' => 5]);
    $monitoring->recordRequest(200, 12.3, [
        'method' => 'GET',
        'path' => '/health',
        'request_id' => 'req_123',
    ]);
    $monitoring->recordDebugStats([
        'queries' => 2,
        'messages' => 1,
        'errors' => 0,
        'slow_queries' => 1,
        'request_ms' => 12.3,
        'memory_mb' => 3.2,
    ]);
    $metrics = $monitoring->metrics();
    if (!isset($metrics['requests_total'])) {
        throw new RuntimeException('Metrics not available.');
    }
    if (($metrics['requests_status']['200'] ?? 0) !== 1) {
        throw new RuntimeException('Status counters not tracked.');
    }
    if (($metrics['debug']['queries_total'] ?? 0) < 2) {
        throw new RuntimeException('Debug stats not tracked.');
    }
    if (($metrics['recent_traces'][0]['path'] ?? '') !== '/health') {
        throw new RuntimeException('Recent traces not captured.');
    }
    echo "monitoring ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "monitoring failed: " . $e->getMessage() . "\n");
    exit(1);
}
