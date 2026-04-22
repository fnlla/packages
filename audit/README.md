**FNLLA/AUDIT**

Audit logging helpers for fnlla (finella). Stores simple change history (who/what/when).

**INSTALLATION**
```bash
composer require fnlla/audit
```

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Audit\AuditServiceProvider`

**SCHEMA**
```php
use Fnlla\\Audit\AuditSchema;

AuditSchema::ensure($pdo);
```

Default table name: `audit_log`

**USAGE**
```php
use Fnlla\\Audit\AuditLogger;

$logger = app()->make(AuditLogger::class);
$logger->record('post.saved', 'post', 123, ['changed' => ['title']]);
```

**CONFIGURATION**
Optional `config/audit/audit.php`:
```php
return [
    'auto_migrate' => false,
    'table' => 'audit_log',
];
```

**TESTING**
```bash
php tests/smoke.php
```
