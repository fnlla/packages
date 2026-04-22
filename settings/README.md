**FNLLA/SETTINGS**

Key/value settings store for fnlla (finella). Useful for runtime configuration in admin panels or multi-tenant apps.

**INSTALLATION**
```bash
composer require fnlla/settings
```

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Settings\SettingsServiceProvider`

**SCHEMA**
Use the schema helper or migrations:
```php
use Fnlla\\Settings\SettingsSchema;

SettingsSchema::ensure($pdo);
```

Default table name: `settings`

**USAGE**
```php
use Fnlla\\Settings\SettingsStore;

$store = app()->make(SettingsStore::class);
$store->set('site_title', 'My Website');
$title = $store->get('site_title', 'Default');
```

Check readiness:
```php
if (!$store->ready()) {
    // Run SettingsSchema::ensure(...) or migrations.
}
```

**CONFIGURATION**
Optional `config/settings/settings.php`:
```php
return [
    'auto_migrate' => false,
    'table' => 'settings',
];
```

**TESTING**
```bash
php tests/smoke.php
```
