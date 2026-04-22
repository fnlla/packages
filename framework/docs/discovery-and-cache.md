**DISCOVERY AND CACHE**

fnlla (finella) can auto-discover service providers from Composer packages and cache the results.

**DISCOVERY**
Discovery reads `vendor/composer/installed.json` and collects providers from:
```
extra.fnlla.providers
```

**CACHE**
The cache file lives at:
**-** `bootstrap/cache/providers.php`

The cache contains:
**-** `providers` (list of FQCN)
**-** `meta` (package name, version, source)

**COMPOSER SCRIPTS**
The starter app runs discovery in `post-install-cmd` and `post-update-cmd` using `bin/fnlla-discover`.

**CLEARING CACHE**
Delete `bootstrap/cache/providers.php` and re-run `bin/fnlla-discover`.

**ROUTES CACHE**
Routes cache can be compiled with `Fnlla\\Http\RouteCacheCompiler` or `routes:cache`.
Set `routes_cache_strict=false` if you want cache generation to emit a disabled cache file
when closures are present (the runtime will ignore it and load routes normally).

**TROUBLESHOOTING**
**-** Ensure the package exposes `extra.fnlla.providers`.
**-** Ensure the class exists and is autoloadable.
**-** Check disabled rules in `config/providers/providers.php`.
