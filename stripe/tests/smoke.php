<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Stripe\StripeManager;

if (!class_exists(\Stripe\StripeClient::class)) {
    echo "stripe skip (stripe sdk missing)\n";
    exit(0);
}

$manager = new StripeManager([
    'enabled' => true,
    'secret' => 'sk_test_123',
    'webhook_secret' => 'whsec_123',
]);

try {
    $client = $manager->client();
    if (!$client instanceof \Stripe\StripeClient) {
        throw new RuntimeException('Stripe client not resolved.');
    }
    echo "stripe ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "stripe failed: " . $e->getMessage() . "\n");
    exit(1);
}
