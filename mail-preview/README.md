**FNLLA/MAIL-PREVIEW**

A tiny mail preview package for fnlla (finella). It provides a preview route and a publish command for templates.

**INSTALLATION**
```bash
composer require fnlla/mail-preview
```

**PUBLISH TEMPLATES**
Register the command in `config/console/console.php`, then run:
```bash
php bin/fnlla mail-preview:publish --app=.
```

**REGISTER ROUTES**
```php
use Fnlla\\Http\Router;
use Fnlla\\MailPreview\MailPreviewRoutes;

return static function (Router $router): void {
    MailPreviewRoutes::register($router);
};
```

**PREVIEW**
Open `/mail/preview` in the browser. You can pass `?template=mail/preview` to override the view.

**NOTES**
**-** Only templates under `resources/views/mail/` are allowed for safety.
