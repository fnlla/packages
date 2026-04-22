<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Maintenance\MaintenanceMiddleware;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$app = new Container();
$configRepo = ConfigRepository::fromRoot(getcwd());
$config = $configRepo;

$middleware = new MaintenanceMiddleware($config);
ok($middleware instanceof MaintenanceMiddleware, 'MaintenanceMiddleware created');

echo "Maintenance smoke tests OK\n";
