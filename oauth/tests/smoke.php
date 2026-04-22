<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\OAuth\OAuthManager;

if (!class_exists(\League\OAuth2\Client\Provider\GenericProvider::class)) {
    echo "oauth skip (league/oauth2-client missing)\n";
    exit(0);
}

$manager = new OAuthManager([
    'providers' => [
        'demo' => [
            'client_id' => 'id',
            'client_secret' => 'secret',
            'redirect_uri' => 'http://localhost/callback',
            'authorize_url' => 'https://example.test/auth',
            'token_url' => 'https://example.test/token',
            'resource_url' => 'https://example.test/me',
        ],
    ],
]);

try {
    $url = $manager->authorizeUrl('demo');
    if (!is_string($url) || $url === '') {
        throw new RuntimeException('Authorize URL not generated.');
    }
    echo "oauth ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "oauth failed: " . $e->getMessage() . "\n");
    exit(1);
}
