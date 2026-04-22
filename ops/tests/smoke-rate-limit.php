<?php

declare(strict_types=1);

require __DIR__ . '/../../_shared/tests/bootstrap.php';

use Fnlla\Cache\ArrayCacheStore;
use Fnlla\Cache\CacheManager;
use Fnlla\Core\Container;
use Fnlla\RateLimit\RateLimitServiceProvider;
use Fnlla\RateLimit\RateLimiter;
use Fnlla\RateLimit\RateLimitMiddleware;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Uri;

function ok(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
}

$app = new Container();
$cache = new CacheManager(['driver' => 'array']);
$app->instance(CacheManager::class, $cache);

$provider = new RateLimitServiceProvider($app);
$provider->register($app);

$limiter = $app->make(RateLimiter::class);
ok($limiter instanceof RateLimiter, 'RateLimiter resolved');

$key = 'test-rate';
ok($limiter->attempt($key, 1, 60) === true, 'first attempt allowed');
ok($limiter->attempt($key, 1, 60) === false, 'second attempt blocked');

// Middleware 429 behaviour
$request = new Request('GET', new Uri('http://localhost/test'), [], null, ['REMOTE_ADDR' => '127.0.0.1']);
$middleware = new RateLimitMiddleware($limiter, 1, 60, 'ip');

$response = $middleware($request, fn () => Response::text('ok'));
ok($response instanceof Response, 'middleware returns response');
ok($response->getStatusCode() === 200, 'first request allowed');

$response = $middleware($request, fn () => Response::text('ok'));
ok($response->getStatusCode() === 429, 'second request blocked');
ok($response->getHeaderLine('Retry-After') !== '', 'retry-after header');

echo "Rate limit smoke tests OK\n";
