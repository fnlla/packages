<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Audit\AuditLogger;
use Fnlla\Audit\AuditRepository;
use Fnlla\Audit\AuditSchema;
use Fnlla\Database\ConnectionManager;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$connections = new ConnectionManager([
    'driver' => 'sqlite',
    'path' => ':memory:',
]);

$pdo = $connections->connection();
AuditSchema::ensure($pdo);

$repo = new AuditRepository($connections);
$logger = new AuditLogger($repo);
$logger->record('test.action', 'entity', 123, ['foo' => 'bar'], ['user_id' => 1]);

$items = $repo->latest(10);
ok(count($items) === 1, 'audit record created');
ok(($items[0]['action'] ?? '') === 'test.action', 'audit action stored');

echo "Audit smoke tests OK\n";
