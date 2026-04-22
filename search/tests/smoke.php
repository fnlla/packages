<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Search\MeilisearchIndexHttpClient;
use Fnlla\Search\SearchManager;

$manager = new SearchManager([
    'driver' => 'meilisearch',
    'meilisearch' => [
        'host' => 'http://127.0.0.1:7700',
        'key' => '',
    ],
]);

try {
    $client = $manager->client();
    if (!$client instanceof \Fnlla\Search\SearchClientInterface) {
        throw new RuntimeException('Search client not resolved.');
    }
    $index = $client->index('products');
    if (!$index instanceof MeilisearchIndexHttpClient) {
        throw new RuntimeException('HTTP index client not resolved.');
    }
    echo "search ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "search failed: " . $e->getMessage() . "\n");
    exit(1);
}
