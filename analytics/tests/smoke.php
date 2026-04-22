<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Analytics\AnalyticsClient;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$client = new AnalyticsClient();
$client->track('smoke', ['ok' => true]);

ok($client instanceof AnalyticsClient, 'AnalyticsClient created');

echo "Analytics smoke tests OK\n";
