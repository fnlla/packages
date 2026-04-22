**CONFIGURATION**

fnlla (finella) loads configuration from `config/**/*.php` at the application root.

**LOADING RULES**
**-** `config/app.php` is loaded first and merged into the root config.
**-** Other files become nested keys (e.g. `config/cache/cache.php` -> `cache`).
**-** Subdirectories can group related configs. The loader maps directory/file names into
  keys: `config/ai/policy.php` -> `ai_policy`, `config/cache/cache_static.php` -> `cache_static`,
  `config/docs/docs.php` -> `docs`.
**-** `config/routes.php` is reserved for route cache and is not loaded as config.
Configuration can also be loaded from a single file via `APP_CONFIG_PATH`, or from a cache file via `APP_CONFIG_CACHE`.

**ACCESS**
```php
$config = $app->configRepository();
$debug = $config->get('debug', false);
```
`$app->config()` is an alias for the configuration repository.

**CORE KEYS**
**-** `debug` (bool)
**-** `timezone` (string)
**-** `locale` (string)
**-** `base_path` (string)
**-** `trusted_proxies` (array|string)
**-** `routes_cache` (string)
**-** `routes_cache_strict` (bool)
**-** `providers` (array)
**-** `plugins` (array)
**-** `schema` (array)

**HTTP CONFIG**
`config/http/http.php`
**-** `global` (array of middleware)
**-** `middleware_groups` (group definitions)
**-** `middleware_aliases` (alias => middleware class/callable)
**-** `request_id_header` (bool, default true)
**-** `trace_id_header` (bool, default true)
**-** `span_id_header` (bool, default true)

**ROUTES CACHE**
Routes cache is intended for production and is skipped when `APP_DEBUG=true` or `APP_ENV=local`.
Cached routes require string handlers and middleware, so closures are not cacheable.
Set `routes_cache_strict=false` to allow `routes:cache` to write a disabled cache file instead of failing
when non-cacheable handlers are present (the runtime will ignore it and load routes normally).
Compile routes using `Fnlla\\Http\RouteCacheCompiler`:
```php
use Fnlla\\Http\RouteCacheCompiler;

$compiler = new RouteCacheCompiler();
$path = $compiler->compile();
```

**VIEWS**
`config/app.php`
**-** `views_path` (string)

**LOG**
`config/log/log.php`
**-** `path`, `level`
**-** `format` (`line` or `json`)
**-** `include_request_id` (bool)
**-** `context` (array, e.g. `app`, `env`, `version`)

**ENVIRONMENT**
The framework does not load `.env` files. The starter app uses `Fnlla\\Support\Dotenv` and an `env()` helper.
