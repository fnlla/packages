<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Cors\CorsMiddleware;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Http\Uri;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

/**
 * @param array<string, string> $headers
 */
function request(string $method, string $url, array $headers = []): Request
{
    return new Request($method, new Uri($url), $headers, Stream::fromString(''), ['REMOTE_ADDR' => '127.0.0.1']);
}

$middleware = new CorsMiddleware(new ConfigRepository([
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['https://APP.EXAMPLE.TEST:443'],
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization'],
        'allow_credentials' => true,
        'max_age' => 600,
    ],
]));
ok($middleware instanceof CorsMiddleware, 'CorsMiddleware created');

$response = $middleware(
    request('GET', 'https://api.example.test/docs', [
        'Origin' => 'https://app.example.test',
    ]),
    static fn ($request): Response => Response::text('ok')
);
ok($response->getHeaderLine('Access-Control-Allow-Origin') === 'https://app.example.test', 'exact origin allowed with normalization');
ok($response->getHeaderLine('Vary') === 'Origin', 'vary header set for non-wildcard origin');

$preflightMiddleware = new CorsMiddleware(new ConfigRepository([
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['https://app.example.test'],
        'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
        'allowed_headers' => null,
        'allow_credentials' => false,
        'max_age' => 300,
    ],
]));

$preflight = $preflightMiddleware(
    request('OPTIONS', 'https://api.example.test/docs', [
        'Origin' => 'https://app.example.test',
        'Access-Control-Request-Method' => 'GET',
        'Access-Control-Request-Headers' => 'Authorization, X-Custom, Invalid Header',
    ]),
    static fn ($request): Response => Response::text('unreachable', 500)
);

ok($preflight->getStatusCode() === 204, 'preflight returns 204');
ok(
    $preflight->getHeaderLine('Access-Control-Allow-Headers') === 'Authorization, X-Custom',
    'preflight reflects only safe request headers when allowed_headers is null'
);

$vary = strtolower($preflight->getHeaderLine('Vary'));
ok(str_contains($vary, 'origin'), 'preflight vary includes origin');
ok(str_contains($vary, 'access-control-request-method'), 'preflight vary includes request method');
ok(str_contains($vary, 'access-control-request-headers'), 'preflight vary includes request headers');

$misconfigured = new CorsMiddleware(new ConfigRepository([
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['*'],
        'allow_credentials' => true,
    ],
]));

$blocked = $misconfigured(
    request('GET', 'https://api.example.test/docs', [
        'Origin' => 'https://evil.example.test',
    ]),
    static fn ($request): Response => Response::text('ok')
);

ok($blocked->getHeaderLine('Access-Control-Allow-Origin') === '', 'wildcard + credentials is blocked (fail closed)');

echo "CORS smoke tests OK\n";
