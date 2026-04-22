**FNLLA/DOCS**

Documentation automation for fnlla (finella) apps.

**FEATURES**
**-** Generates technical and user-guide Markdown skeletons from your app.
**-** Includes a built-in safe Markdown renderer (no external parser dependency).
**-** Stores generated docs under `storage/docs/generated` by default.
**-** Supports manual docs in `storage/docs/manual` that override generated docs.
**-** Works with the starter Docs UI (searches manual, generated, then published docs).

**INSTALLATION**
```bash
composer require fnlla/docs
```

**CONFIGURATION**
Create `config/docs/docs.php` in your app:
```php
<?php

declare(strict_types=1);

$root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
$storage = rtrim($root, '/\\') . '/storage/docs';

return [
    'paths' => [
        'manual' => $storage . '/manual',
        'generated' => $storage . '/generated',
        'published' => $root . '/resources/docs',
    ],
];
```

**CLI**
Register the command in `config/console/console.php`:
```php
return [
    'commands' => [
        Fnlla\\Docs\Commands\DocsGenerateCommand::class,
        Fnlla\\Docs\Commands\DocsSyncCommand::class,
    ],
];
```
Run generation:
```bash
php bin/fnlla docs:generate
```
Generate and publish in one step:
```bash
php bin/fnlla docs:generate --publish
```
Optional override:
```bash
php bin/fnlla docs:generate --publish --publish-target=/path/to/published/docs
```
Sync monorepo docs into the app:
```bash
php bin/fnlla docs:sync --app=.
```

**PUBLISHING**
To ship compiled docs with your app, publish them into the configured `published` path:
```php
$manager = app(Fnlla\\Docs\DocsManager::class);
$report = $manager->publish();
```
Publishing copies generated docs into `resources/docs` (by default) and overlays manual docs when they exist.

**NOTES**
**-** Generated files are safe to delete; re-run the command to rebuild them.
**-** Manual docs are never overwritten by automation.
