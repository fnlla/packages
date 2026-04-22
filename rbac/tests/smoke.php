<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Database\ConnectionManager;
use Fnlla\Rbac\RbacManager;
use Fnlla\Rbac\RbacSchema;

if (!class_exists(RbacManager::class)) {
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src';
    require_once $base . DIRECTORY_SEPARATOR . 'RbacSchema.php';
    require_once $base . DIRECTORY_SEPARATOR . 'RbacManager.php';
}

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

$manager = new RbacManager(new ConnectionManager([
    'driver' => 'sqlite',
    'path' => ':memory:',
]), null, ['auto_migrate' => true, 'cache_ttl' => 0]);

$manager->ensureSchema();

$manager->assignRole(1, 'admin');
$manager->grantPermissionToRole('admin', 'posts.update');

ok($manager->hasRole(['id' => 1], 'admin') === true, 'role assigned');
ok($manager->hasPermission(['id' => 1], 'posts.update') === true, 'permission assigned');

echo "RBAC smoke tests OK\n";
