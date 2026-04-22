**FNLLA/DEBUGBAR**

Modern debugging tools for development environments, with request headers and an embedded in-browser panel.

**INSTALLATION**
```bash
composer require fnlla/debugbar
```

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Debugbar\DebugbarServiceProvider`

**WHAT YOU GET**
**-** Response headers: `X-Debug-Queries`, `X-Debug-Messages`, `X-Debug-Errors`, `X-Debug-Time-Ms`, `X-Debug-Slow-Queries`, `X-Debug-Memory-Mb`
**-** Embedded panel for HTML responses with tabs: Summary, Queries, Timeline, Headers, Messages, Errors
**-** JS assets served from `/_fnlla/debugbar.js` (keeps panel logic out of PHP view strings)
**-** Query filter input, slow-query highlighting, and one-click SQL copy actions
**-** Request/response header inspection tab for quick diagnostics
**-** Persistent UI state (open/closed + active tab) per browser session
**-** Keyboard shortcut: `Ctrl+Shift+D` to toggle panel

**ENV FLAGS (OPTIONAL)**
**-** `DEBUGBAR_UI_ENABLED=1` (default: enabled)
**-** `DEBUGBAR_SLOW_QUERY_MS=25`
**-** `DEBUGBAR_MAX_ROWS=120`

**CAPTURING SQL**
Use `DebugPDO` instead of `PDO` in your database connection:
```php
use Fnlla\\Debugbar\DebugPDO;

$pdo = new DebugPDO($dsn, $user, $pass, $options);
```

**COLLECTING EVENTS MANUALLY**
```php
use Fnlla\\Debugbar\DebugbarCollector;

DebugbarCollector::addMessage('info', 'Invoice generated', ['invoice_id' => 123]);
DebugbarCollector::mark('billing.rendered');
```

**NOTES**
Only enable debugbar in non-production environments.
