<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\CacheStatic\StaticCacheMiddleware;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$configRepo = ConfigRepository::fromRoot(getcwd());
$config = $configRepo;

$middleware = new StaticCacheMiddleware($config);
ok($middleware instanceof StaticCacheMiddleware, 'StaticCacheMiddleware created');

echo "Cache static smoke tests OK\n";
