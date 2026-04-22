<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Redirects\RedirectsMiddleware;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$configRepo = ConfigRepository::fromRoot(getcwd());
$config = $configRepo;

$middleware = new RedirectsMiddleware($config);
ok($middleware instanceof RedirectsMiddleware, 'RedirectsMiddleware created');

echo "Redirects smoke tests OK\n";
