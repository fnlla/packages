**MIDDLEWARE**

**OVERVIEW**
Middleware wrap route handlers to perform cross-cutting tasks such as security headers, logging, or CSRF checks.

**GLOBAL MIDDLEWARE**
Defined in `config/http/http.php`:
```php
return [
    'global' => [
        \Fnlla\\SecurityHeaders\SecurityHeadersMiddleware::class,
    ],
];
```
Requires `fnlla/ops`.

**PER-ROUTE MIDDLEWARE**
```php
$router->get('/account', [AccountController::class, 'index'], 'account', [
    \Fnlla\\Auth\Middleware\AuthMiddleware::class,
]);
```
Requires the core Auth module.

**MIDDLEWARE GROUPS**
```php
$router->middlewareGroup('web', [
    \Fnlla\\Csrf\CsrfMiddleware::class,
    \Fnlla\\SecurityHeaders\SecurityHeadersMiddleware::class,
]);
```
Requires the core CSRF module and `fnlla/ops`.

**MIDDLEWARE ALIASES**
Define aliases in `config/http/http.php`:
```php
return [
    'middleware_aliases' => [
        'auth' => \Fnlla\\Auth\Middleware\AuthMiddleware::class,
        'csrf' => \Fnlla\\Csrf\CsrfMiddleware::class,
    ],
];
```
Then use aliases in routes:
```php
$router->get('/account', [AccountController::class, 'index'], 'account', ['auth']);
```

**EXECUTION ORDER**
**-** Global middleware
**-** Group middleware
**-** Route-specific middleware
**-** Handler

**WRITING MIDDLEWARE**
A middleware can be:
**-** a closure: `function (Request $request, callable $next): Response`
**-** a class implementing `Fnlla\\Support\Psr\Http\Server\MiddlewareInterface`

**CORE MIDDLEWARE**
Core middleware ships in the framework:
**-** Auth (`AuthMiddleware`)
**-** CSRF (`CsrfMiddleware`)
**-** Request logging (`RequestLoggerMiddleware`)

**OPTIONAL MIDDLEWARE**
Optional middleware is delivered as packages and only enabled when you install them:
**-** `fnlla/ops` (SecurityHeadersMiddleware, RateLimitMiddleware, CorsMiddleware, RedirectsMiddleware, MaintenanceMiddleware, StaticCacheMiddleware, HoneypotMiddleware)
