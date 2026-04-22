**FNLLA/WEBMAIL**

Webmail backend API for IMAP/POP3 inbox access and SMTP sending. This package
ships API endpoints and a pluggable mailbox client interface.

**INSTALLATION**
```bash
composer require fnlla/webmail
```

**REQUIREMENTS**
**-** For IMAP access, enable PHP `ext-imap`.
**-** For runtime IMAP/SMTP settings, install `fnlla/settings` and a database connection.

**SERVICE PROVIDER**
Auto-discovered provider:
**-** `Fnlla\\Webmail\WebmailServiceProvider`

**CONFIGURATION**
Create `config/webmail/webmail.php`:
```php
return [
    'tenant_scoped' => (bool) env('WEBMAIL_TENANT_SCOPED', false),
    'tenant_prefix' => env('WEBMAIL_TENANT_PREFIX', 'tenant:'),
    'send_async' => (bool) env('WEBMAIL_SEND_ASYNC', false),
    'imap' => [
        'host' => env('WEBMAIL_IMAP_HOST', ''),
        'port' => (int) env('WEBMAIL_IMAP_PORT', 993),
        'flags' => env('WEBMAIL_IMAP_FLAGS', '/imap/ssl'),
        'username' => env('WEBMAIL_IMAP_USER', ''),
        'password' => env('WEBMAIL_IMAP_PASS', ''),
        'folder' => env('WEBMAIL_IMAP_FOLDER', 'INBOX'),
    ],
    'smtp' => [
        'dsn' => env('WEBMAIL_SMTP_DSN', ''),
        'host' => env('WEBMAIL_SMTP_HOST', ''),
        'port' => (int) env('WEBMAIL_SMTP_PORT', 587),
        'username' => env('WEBMAIL_SMTP_USER', ''),
        'password' => env('WEBMAIL_SMTP_PASS', ''),
        'encryption' => env('WEBMAIL_SMTP_ENCRYPTION', 'tls'),
        'from_address' => env('WEBMAIL_SMTP_FROM_ADDRESS', ''),
        'from_name' => env('WEBMAIL_SMTP_FROM_NAME', ''),
    ],
    'security' => [
        'require_encryption' => (bool) env('WEBMAIL_REQUIRE_ENCRYPTION', false),
        'imap_host_allowlist' => ['imap.example.com'],
        'smtp_host_allowlist' => ['smtp.example.com'],
        'test_enabled' => (bool) env('WEBMAIL_TEST_ENABLED', true),
        'test_recipient_allowlist' => ['ops@example.com'],
    ],
];
```
SMTP defaults are resolved from `webmail.smtp` first, then `mail` config if not provided.

**API ROUTES**
```php
use Fnlla\\Webmail\WebmailRoutes;

WebmailRoutes::register($router, [
    'prefix' => '/api/webmail',
    'middleware' => ['auth'],
    // 'rate' => '30,1,ip',
]);
```

Endpoints:
**-** `GET /api/webmail/settings`
**-** `PUT /api/webmail/settings`
**-** `POST /api/webmail/test`
**-** `GET /api/webmail/folders`
**-** `GET /api/webmail/messages?folder=INBOX`
**-** `GET /api/webmail/messages/{uid}?folder=INBOX`
**-** `DELETE /api/webmail/messages/{uid}?folder=INBOX`
**-** `POST /api/webmail/send`

**SETTINGS API (OPTIONAL)**
If `fnlla/settings` is installed, you can store IMAP/SMTP credentials at runtime
and build a UI for end users. Settings are stored in the `settings` table under keys:
**-** `webmail.imap.*` (`host`, `port`, `flags`, `username`, `password`, `folder`)
**-** `webmail.smtp.*` (`dsn`, `host`, `port`, `username`, `password`, `encryption`, `from_address`, `from_name`)
Ensure the `settings` table exists (see `fnlla/settings` schema helper).
Credentials are stored as strings in the settings table; secure the database at rest.
If `webmail.tenant_scoped` is enabled, keys are prefixed with the tenant id (requires `fnlla/tenancy`).

Example update payload:
```json
{
  "imap": {
    "host": "imap.example.com",
    "port": 993,
    "flags": "/imap/ssl",
    "username": "inbox@example.com",
    "password": "secret",
    "folder": "INBOX"
  },
  "smtp": {
    "host": "smtp.example.com",
    "port": 587,
    "username": "inbox@example.com",
    "password": "secret",
    "encryption": "tls",
    "from_address": "inbox@example.com",
    "from_name": "Example Team"
  }
}
```

`GET /api/webmail/settings` returns `password_set` instead of the raw password.
Passwords are encrypted at rest when `APP_KEY` (or `WEBMAIL_SETTINGS_KEY`) is set.
`GET /api/webmail/settings` also returns `store_ready` (and `store_error` when false).
If the settings table is missing, `PUT /api/webmail/settings` returns `503`.

**TEST ENDPOINT**
Use `POST /api/webmail/test` to validate connectivity.
Example payload:
```json
{
  "imap": true,
  "smtp": true,
  "to": "ops@example.com"
}
```
If `imap`/`smtp` are omitted they default to `true`.

**SECURITY CONTROLS (RECOMMENDED)**
**-** Set `webmail.security.require_encryption = true` to block saving plaintext passwords.
**-** Use allowlists: `webmail.security.imap_host_allowlist` and `webmail.security.smtp_host_allowlist`.
**-** Disable test endpoint in production: `webmail.security.test_enabled = false`.
**-** If multi-tenant, enable `webmail.tenant_scoped = true`.

**ASYNC SEND**
Enable `webmail.send_async = true` to enqueue outgoing messages (requires `fnlla/queue`).

**CUSTOM MAILBOX CLIENT**
Bind your own `MailboxClientInterface` if you do not want to use IMAP:
```php
use Fnlla\\Webmail\MailboxClientInterface;

$app->singleton(MailboxClientInterface::class, fn () => new YourMailboxClient());
```

**TESTING**
```bash
php tests/smoke.php
```
