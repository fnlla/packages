**FNLLA/RBAC**

Roles and permissions for fnlla (finella) with gate integration.

**INSTALLATION**
```bash
composer require fnlla/rbac
```

**CONFIGURATION**
Create `config/rbac/rbac.php`:
```php
return [
    'cache_ttl' => 300,
    'auto_migrate' => false,
];
```

**MIGRATIONS**
This package ships schema helpers. You can either:
**-** copy the migrations into your app, or
**-** call `RbacSchema::ensure($pdo)` during setup.

**USAGE**
```php
$rbac = app()->make(\Fnlla\\Rbac\RbacManager::class);

$rbac->assignRole($userId, 'admin');
$rbac->grantPermissionToRole('admin', 'posts.update');

if (can('permission', 'posts.update')) {
    // allowed
}
```

**USER HELPERS**
If your user model uses the `Fnlla\\Rbac\HasRoles` trait:
```php
if ($user->hasRole('admin')) {
    // ...
}

if ($user->can('posts.update')) {
    // ...
}
```

**GATE INTEGRATION**
The service provider defines two abilities:
**-** `role` � `can('role', 'admin')`
**-** `permission` � `can('permission', 'posts.update')`

**SCHEMA**
Tables created by `RbacSchema`:
**-** `roles`
**-** `permissions`
**-** `role_user`
**-** `permission_role`

**TESTING**
```bash
php tests/smoke.php
```
