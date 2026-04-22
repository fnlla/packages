**FNLLA/SENTRY**

Sentry error tracking adapter for fnlla (finella).

**INSTALLATION**
```bash
composer require fnlla/sentry
```

**CONFIGURATION**
Create `config/sentry/sentry.php` and set `.env`:
```
SENTRY_ENABLED=1
SENTRY_DSN=https://examplePublicKey@o0.ingest.sentry.io/0
SENTRY_ENV=prod
SENTRY_RELEASE=2.5.1
```

**USAGE**
```php
use Fnlla\\Sentry\SentryManager;

$sentry = app()->make(SentryManager::class);
$sentry->captureMessage('Payment failed', \Sentry\Severity::error());
```
