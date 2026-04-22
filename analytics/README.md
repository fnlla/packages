**FNLLA/ANALYTICS**

Analytics event helpers for fnlla (finella).

**INSTALLATION**
```bash
composer require fnlla/analytics
```
The package registers `AnalyticsServiceProvider` via auto-discovery.

**USAGE**
```php
use Fnlla\\Analytics\AnalyticsClient;

$analytics = $app->make(AnalyticsClient::class);
$analytics->track('lead_submitted', [
    'source' => 'contact_form',
    'page' => '/services/ai-services',
]);
```

If a logger is bound (for example via the core logging module), events are logged with
context. Otherwise tracking is a no-op.

**CONFIGURATION**
`config/analytics/analytics.php` (example):
```php
return [
    'enabled' => env('ANALYTICS_ENABLED', true),
];
```

**TESTING**
```bash
php tests/smoke.php
```
