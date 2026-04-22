<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Http\Uri;
use Fnlla\Runtime\RequestContext;
use Fnlla\Runtime\ResetManager;
use Fnlla\SecurityHeaders\SecurityHeadersMiddleware;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$config = new ConfigRepository([
    'security' => [
        'headers' => [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        ],
        'csp' => "default-src 'self'; script-src 'self' 'nonce-%s'",
        'csp_report_only' => false,
    ],
]);
$context = new RequestContext(new ResetManager(), 'req-1', microtime(true), null, 'nonce-test');

$middleware = new SecurityHeadersMiddleware($config, $context);
ok($middleware instanceof SecurityHeadersMiddleware, 'SecurityHeadersMiddleware created');
$request = new Request('GET', new Uri('https://app.example.test/'), [], Stream::fromString(''));
$response = $middleware($request, static fn ($req): Response => Response::html('<html><body>ok</body></html>'));

ok($response->getHeaderLine('X-Content-Type-Options') === 'nosniff', 'security header applied');
ok($response->getHeaderLine('Strict-Transport-Security') !== '', 'custom header applied');
ok(
    $response->getHeaderLine('Content-Security-Policy') === "default-src 'self'; script-src 'self' 'nonce-nonce-test'",
    'CSP nonce placeholder replaced'
);

$reportOnlyConfig = new ConfigRepository([
    'security' => [
        'csp' => "default-src 'self'; script-src 'self' 'nonce-{nonce}'",
        'csp_report_only' => true,
    ],
]);

$reportOnlyMiddleware = new SecurityHeadersMiddleware($reportOnlyConfig, $context);
$reportOnlyResponse = $reportOnlyMiddleware($request, static fn ($req): Response => Response::html('<html><body>ok</body></html>'));
ok($reportOnlyResponse->getHeaderLine('Content-Security-Policy') === '', 'enforced CSP omitted in report-only mode');
ok(
    $reportOnlyResponse->getHeaderLine('Content-Security-Policy-Report-Only') === "default-src 'self'; script-src 'self' 'nonce-nonce-test'",
    'report-only CSP header set with nonce token'
);

echo "Security headers smoke tests OK\n";
