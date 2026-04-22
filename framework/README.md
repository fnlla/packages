**fnlla (finella) FRAMEWORK**

fnlla (finella) is a lightweight, AI-assisted (optional) PHP web framework focused on clarity, small surface area, and production-ready defaults. The core is intentionally minimal: kernel, router, container, configuration, and error handling.
AI capabilities are opt-in and sit on top of the core runtime via optional packages and governance controls.

**REQUIREMENTS**
**-** PHP >= 8.5

**INSTALLATION**
Offline install:
**-** Add `fnlla/framework` to `composer.json` in `require`.
**-** Configure local path repositories (see `documentation/src/getting-started.md`).
**-** Run `composer install`.

**QUICKSTART**

**BOOTSTRAP/APP.PHP**
```php
<?php

declare(strict_types=1);

use Fnlla\\Core\Application;
use Fnlla\\Core\ConfigRepository;
use Fnlla\\Http\HttpKernel;

$root = dirname(__DIR__);
if (!defined('APP_ROOT')) {
    define('APP_ROOT', $root);
}

$configRepo = ConfigRepository::fromRoot($root);
$app = new Application($root, $configRepo);

return new HttpKernel($app);
```

**PUBLIC/INDEX.PHP**
```php
<?php

declare(strict_types=1);

use Fnlla\\Contracts\Http\KernelInterface;
use Fnlla\\Http\Request;

require __DIR__ . '/../vendor/autoload.php';

$kernel = require __DIR__ . '/../bootstrap/app.php';
if (!$kernel instanceof KernelInterface) {
    http_response_code(500);
    echo 'Bootstrap must return a KernelInterface.';
    exit(1);
}

$request = Request::fromGlobals();
$response = $kernel->handle($request);
$response->send();
```

**WARM KERNEL (LONG-RUNNING)**
For long-running servers, boot once and reuse the kernel per request:
```php
use Fnlla\\Http\HttpKernel;

$kernel = new HttpKernel();
$kernel->boot();
```
Ensure request-scoped state uses scoped services or resetters, for example:
```php
$app->registerResetter(new \App\Support\MyResetter());
```

**TRACING HEADERS**
fnlla (finella) includes `X-Request-Id`, `X-Trace-Id`, and `X-Span-Id` on responses by default.
Disable via `config/http/http.php` (`request_id_header`, `trace_id_header`, `span_id_header`).

**ROUTES CACHE (COMPILE)**
Generate a routes cache file for production deployments:
```php
use Fnlla\\Http\RouteCacheCompiler;

$compiler = new RouteCacheCompiler();
$path = $compiler->compile();
```

**ROUTING EXAMPLE**
```php
use Fnlla\\Http\Router;
use Fnlla\\Http\Response;

return static function (Router $router): void {
    $router->get('/', fn () => Response::text('Hello fnlla (finella)'));
    $router->get('/users/{id}', function ($request): Response {
        return Response::text('User ' . $request->getAttribute('id'));
    });
};
```

**MIDDLEWARE EXAMPLE (OPTIONAL MODULE)**
`config/http/http.php`
```php
use Fnlla\\SecurityHeaders\SecurityHeadersMiddleware;

return [
    'global' => [
        SecurityHeadersMiddleware::class,
    ],
];
```
Requires `fnlla/ops`.

**CONFIGURATION**
fnlla (finella) loads configuration from `config/**/*.php`. See `documentation/src/framework.md` for the full reference.

**ENVIRONMENT**
The framework does not load `.env` by itself. The starter app uses `Fnlla\\Support\Dotenv` and provides an `env()` helper.

**CORE VS OPTIONAL MODULES**
Framework core now includes the full app foundation: HTTP kernel, router, container, config, error handling,
logging, request tracing, sessions, auth, cookies, CSRF, database, cache, ORM, and CLI tooling.
Optional packages add specialised capabilities.

**OPTIONAL PACKAGES**
**-** `fnlla/ops` - security headers, CORS, rate limiting, redirects, maintenance, static cache, and forms.
**-** `fnlla/queue` - queue manager (sync/database/redis drivers).
**-** `fnlla/scheduler` - schedule registry and `schedule:run`.
**-** `fnlla/mail` - Symfony Mailer adapter.
**-** `fnlla/ai` - AI provider integration, policy controls, router, telemetry, and RAG helpers.
**-** `fnlla/debugbar` - debug tooling for development.
**-** `fnlla/tenancy` - multi-tenant context and model scoping.

**ENABLING OPTIONAL MODULES**
**-** Install the package with Composer.
**-** Ensure its provider is auto-discovered (or register manually).
**-** Add any middleware to your `config/http/http.php` pipeline.

Example:
**-** Add `fnlla/ops` to `composer.json` in `require`.
**-** Run `composer install`.
```php
use Fnlla\\RateLimit\RateLimitMiddleware;

return [
    'global' => [
        RateLimitMiddleware::class,
    ],
];
```

**NOT INCLUDED BY DESIGN**
**-** Cron/daemon management (use system cron or supervisor)
**-** CMS/editor experience (use `fnlla/content` or a dedicated CMS)

**STABILITY & VERSIONING**
**-** fnlla (finella) follows Semantic Versioning (SemVer).
**-** The 3.x line is the current stable core.
**-** Patch releases (3.x.y) contain fixes only, no breaking changes.
**-** Minor releases (3.x.0) add backward-compatible features.
**-** Major releases (4.0.0+) may include breaking changes with upgrade notes.

**CREDITS**
**-** Author / Organisation: [TechAyo](https://techayo.co.uk)
**-** Project Manager: Marcin Kordyaczny

**LICENCE**
Proprietary (see LICENSE.md).
