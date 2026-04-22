<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Deploy\Commands\DeployHealthCommand;
use Fnlla\Deploy\Commands\DeployWarmupCommand;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$health = new DeployHealthCommand();
$warmup = new DeployWarmupCommand();

ok($health->getName() === 'deploy:health', 'Deploy health command name');
ok($warmup->getName() === 'deploy:warmup', 'Deploy warmup command name');

echo "Deploy smoke tests OK\n";
