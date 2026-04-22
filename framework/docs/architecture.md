**ARCHITECTURE**

**REQUEST LIFECYCLE**
**-** `public/index.php` loads Composer autoload and the bootstrap.
**-** `bootstrap/app.php` builds the container and returns the HTTP kernel.
**-** `HttpKernel` builds request context, loads config, and sets up middleware.
**-** `Router` matches the route and invokes the handler.
**-** A `Response` is returned and sent.

**ASCII DIAGRAM**
```
Request
  |
  v
public/index.php
  |
  v
bootstrap/app.php
  |
  v
HttpKernel
  |
  v
Router -> Middleware -> Handler
  |
  v
Response
```

**WARM KERNEL (LONG-RUNNING)**
You can boot once and reuse the kernel for long-running servers:
```php
$kernel = new \Fnlla\\Http\HttpKernel();
$kernel->boot();
```
This avoids reloading providers and plugins on each request. Register resetters for per-request cleanup:
```php
$app->registerResetter(new \App\Support\MyResetter());
```

**CONTAINER AND DI**
The container is used by the router to resolve controllers and inject dependencies into handlers via `container->call()`.

**CONFIGURATION**
Configuration is loaded from `config/**/*.php` through `ConfigRepository::fromRoot()`.

**ROUTES CACHE**
Routes cache is intended for production and is skipped when `APP_DEBUG=true` or `APP_ENV=local`.
Cached routes require string handlers and middleware, so closures are not cacheable.

**PROVIDERS AND EXTENSIONS**
Service providers register services and boot integration features. Discovery and caching are provided by the support layer and can be enabled in the app bootstrap.
