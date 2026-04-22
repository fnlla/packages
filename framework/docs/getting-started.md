**GETTING STARTED**

This guide shows the minimal steps required to run a fnlla (finella) application using the framework package.

**1) INSTALL**
Offline install:
**-** Add `fnlla/framework` to `composer.json` in `require`.
**-** Configure local path repositories (see `documentation/src/getting-started.md`).
**-** Run `composer install`.

**2) CREATE THE BOOTSTRAP**
`bootstrap/app.php`
```php
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

**3) CREATE THE ENTRY POINT**
`public/index.php`
```php
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

**4) CREATE THE FIRST ROUTE**
`routes/web.php`
```php
use Fnlla\\Http\Router;
use Fnlla\\Http\Response;

return static function (Router $router): void {
    $router->get('/', fn () => Response::text('Hello fnlla (finella)'));
};
```

**5) RUN LOCALLY**
```bash
php -S localhost:8000 -t public
```

**NEXT STEPS**
**-** See `routing.md` and `middleware.md` for advanced routing.
**-** Configure views with `config/app.php`.
**-** Use packages for database, cache, queue, and mail.
