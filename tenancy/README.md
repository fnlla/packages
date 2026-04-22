**fnlla (finella) TENANCY**

Basic multi-tenant helpers for fnlla (finella) apps. This package provides:
**-** `TenantContext` for storing the current tenant id.
**-** `TenantMiddleware` to resolve the tenant per request.
**-** `TenantModel` base class that scopes queries by `tenant_id`.

**INSTALL**
Included in the monorepo. Install the package and keep provider discovery
enabled (or register the provider manually in `config/providers/providers.php`):
```bash
composer require fnlla/tenancy
```

**CONFIGURE**
Create `config/tenancy/tenancy.php` (starter includes it by default):
```php
return [
    'enabled' => env('TENANCY_ENABLED', false),
    'required' => env('TENANCY_REQUIRED', false),
    'resolver' => env('TENANCY_RESOLVER', 'header'), // header | host | path | auto
    'header' => env('TENANCY_HEADER', 'X-Tenant-Id'),
    'attribute' => env('TENANCY_ATTRIBUTE', 'tenant_id'),
    'required_status' => (int) env('TENANCY_REQUIRED_STATUS', 400),
    'required_message' => env('TENANCY_REQUIRED_MESSAGE', 'Tenant identifier required.'),
    'host' => [
        'base_domain' => env('TENANCY_BASE_DOMAIN', ''),
        'map' => [],
    ],
    'path' => [
        'segment' => (int) env('TENANCY_PATH_SEGMENT', 1),
    ],
];
```

**MIDDLEWARE**
Add the middleware in `config/http/http.php`:
```php
$aliases['tenant'] = Fnlla\\Tenancy\TenantMiddleware::class;
// Optionally enable globally when TENANCY_ENABLED=1
```

**MODELS**
Extend `TenantModel` to auto-scope by tenant id:
```php
use Fnlla\\Tenancy\TenantModel;

final class Project extends TenantModel
{
    protected string $table = 'projects';
}
```

**CUSTOM RESOLVER**
Implement `TenantResolverInterface` and bind it in the container to override
the default resolver.
