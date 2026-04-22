**ROUTING**

**DEFINING ROUTES**
```php
use Fnlla\\Http\Router;
use Fnlla\\Http\Response;

return static function (Router $router): void {
    $router->get('/', fn () => Response::text('Home'));
    $router->post('/contact', fn () => Response::text('OK'));
    $router->add('PUT', '/profile', fn () => Response::text('Updated'));
};
```

**ROUTE PARAMETERS**
```php
$router->get('/users/{id}', function ($request): Response {
    return Response::text('User ' . $request->getAttribute('id'));
});
```
You can constrain parameters with regex: `{id:\\d+}`.

**NAMED ROUTES**
```php
$router->get('/users/{id}', [UserController::class, 'show'], 'users.show');
$url = $router->url('users.show', ['id' => 10]);
```

**CONTROLLERS**
Handlers can be closures, arrays, or `Controller@method` strings:
```php
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->get('/account', 'AccountController@index');
```

**MIDDLEWARE GROUPS**
```php
$router->middlewareGroup('web', [
    \Fnlla\\Csrf\CsrfMiddleware::class,
    \Fnlla\\SecurityHeaders\SecurityHeadersMiddleware::class,
]);
```
Requires the core CSRF module and `fnlla/ops`.

**404 / 405 BEHAVIOUR**
**-** No matching path -> 404
**-** Path matches but method does not -> 405

**DI IN HANDLERS**
If the container is available, handlers are called via `container->call()`:
```php
use Fnlla\\Http\Request;
use Fnlla\\Http\Response;

$router->get('/agent', function (Request $request): Response {
    return Response::text($request->getHeaderLine('User-Agent'));
});
```

**ROUTE CACHING**
Routes can be cached to `storage/cache/routes.php`. Only controller strings/arrays are cacheable; closures are not.
Routes cache is intended for production and is skipped when `APP_DEBUG=true` or `APP_ENV=local`.
Compile routes with `Fnlla\\Http\RouteCacheCompiler` or `routes:cache`.
