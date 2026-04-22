**FAQ**

**WHERE SHOULD I PUT VIEWS?**
By default, views live in `resources/views`. Configure the path via `config/app.php` (`views_path`).

**WHY IS DOCUMENTROOT `public/`?**
It prevents direct access to config, storage, and vendor files.

**HOW DO I ENABLE OR DISABLE A PROVIDER?**
Use `config/providers/providers.php`:
**-** `disabled` rules can disable a provider globally or per environment.
**-** `manual` can add extra providers.

**HOW DO I DEBUG PROVIDER DISCOVERY?**
**-** Delete `bootstrap/cache/providers.php`.
**-** Run `bin/fnlla-discover`.
**-** Check `storage/logs/fnlla-providers.log` if `APP_DEBUG=1`.

**HOW DO I ADD MIDDLEWARE?**
Add it to `config/http/http.php` (global) or pass it to routes.

**HOW DO I ADD A NEW PACKAGE?**
Create a Composer package, expose a service provider, and add it to `extra.fnlla.providers`.
