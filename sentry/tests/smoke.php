<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Sentry\SentryManager;

if (!interface_exists(\Sentry\State\HubInterface::class)) {
    echo "sentry skip (sentry sdk missing)\n";
    exit(0);
}

$manager = new SentryManager([
    'enabled' => true,
    'dsn' => 'https://examplePublicKey@o0.ingest.sentry.io/0',
    'environment' => 'local',
]);

try {
    $manager->init();
    $hub = $manager->hub();
    if (!$hub instanceof \Sentry\State\HubInterface) {
        throw new RuntimeException('Sentry hub not resolved.');
    }
    echo "sentry ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "sentry failed: " . $e->getMessage() . "\n");
    exit(1);
}
