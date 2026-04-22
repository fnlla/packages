<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Forms\HoneypotMiddleware;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$configRepo = ConfigRepository::fromRoot(getcwd());
$config = $configRepo;

$middleware = new HoneypotMiddleware($config);
ok($middleware instanceof HoneypotMiddleware, 'HoneypotMiddleware created');

echo "Forms smoke tests OK\n";
