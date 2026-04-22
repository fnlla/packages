<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Debugbar\DebugbarCollector;
use Fnlla\Debugbar\Middleware\DebugbarMiddleware;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Http\Uri;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Server\RequestHandlerInterface;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

DebugbarCollector::init();
DebugbarCollector::addQuery('select 1', [], 1.2);
DebugbarCollector::addMessage('info', 'hello');
DebugbarCollector::addError('Error', 'boom', __FILE__, __LINE__);
DebugbarCollector::mark('boot');

ok(count(DebugbarCollector::queries()) === 1, 'query collected');
ok(count(DebugbarCollector::messages()) === 1, 'message collected');
ok(count(DebugbarCollector::errors()) === 1, 'error collected');
ok(count(DebugbarCollector::timeline()) >= 1, 'timeline collected');

$middleware = new DebugbarMiddleware();
$request = Request::fromGlobals();

$handler = new class implements RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        DebugbarCollector::addQuery('select 2', ['id' => 1], 12.3, 1, 'execute');
        DebugbarCollector::addMessage('info', 'handler message');
        DebugbarCollector::addError('RuntimeException', 'handler error', __FILE__, __LINE__);
        DebugbarCollector::mark('handler.done');
        return Response::html('<!doctype html><html><body><h1>ok</h1></body></html>');
    }
};

$response = $middleware->process($request, $handler);
ok($response instanceof Response, 'response type');
ok($response->getHeaderLine('X-Debug-Queries') === '1', 'X-Debug-Queries');
ok($response->getHeaderLine('X-Debug-Messages') === '1', 'X-Debug-Messages');
ok($response->getHeaderLine('X-Debug-Errors') === '1', 'X-Debug-Errors');
ok($response->getHeaderLine('X-Debug-Time-Ms') !== '', 'X-Debug-Time-Ms');
ok(str_contains((string) $response->getBody(), 'Fnlla Debugbar'), 'debugbar panel injected');
ok(str_contains((string) $response->getBody(), '<script src="/_fnlla/debugbar.js?v=3.0.0" defer></script>'), 'debugbar js asset injected');

$assetRequest = new Request('GET', new Uri('https://app.example.test/_fnlla/debugbar.js'), [], Stream::fromString(''));
$assetResponse = $middleware->process($assetRequest, $handler);
ok($assetResponse->getStatusCode() === 200, 'debugbar js asset endpoint returns 200');
ok(str_contains($assetResponse->getHeaderLine('Content-Type'), 'application/javascript'), 'debugbar js content type set');
ok(str_contains((string) $assetResponse->getBody(), 'data-fdbg-toggle'), 'debugbar js payload returned');

echo "Debugbar smoke tests OK\n";
