**FNLLA/OPS**

fnlla (finella) ops middleware bundle for security, routing hygiene, and baseline HTTP controls.

**INCLUDES**
**-** Security headers (`Fnlla\\SecurityHeaders\*`)
**-** CORS (`Fnlla\\Cors\*`)
**-** Rate limiting (`Fnlla\\RateLimit\*`)
**-** Redirects (`Fnlla\\Redirects\*`)
**-** Maintenance mode (`Fnlla\\Maintenance\*`)
**-** Static cache (`Fnlla\\CacheStatic\*`)
**-** Forms honeypot (`Fnlla\\Forms\*`)

**INSTALL**
```
composer require fnlla/ops
```

The package registers all middleware service providers via auto-discovery.

**CONFIG**
Each module uses its existing config file:
**-** `config/security/security.php`
**-** `config/cors/cors.php`
**-** `config/rate_limit.php`
**-** `config/redirects/redirects.php`
**-** `config/maintenance/maintenance.php`
**-** `config/cache/cache_static.php`
**-** `config/forms/forms.php`
