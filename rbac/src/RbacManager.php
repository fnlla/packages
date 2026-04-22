<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Rbac;

use Fnlla\Database\ConnectionManager;
use Fnlla\Cache\CacheManager;
use PDO;

final class RbacManager
{
    private int $cacheTtl;
    private bool $autoMigrate;

    public function __construct(
        private ConnectionManager $connections,
        private ?CacheManager $cache = null,
        array $config = []
    ) {
        $this->cacheTtl = (int) ($config['cache_ttl'] ?? 300);
        $this->autoMigrate = (bool) ($config['auto_migrate'] ?? false);
    }

    public function ensureSchema(): void
    {
        RbacSchema::ensure($this->pdo());
    }

    public function assignRole(string|int $userId, string $role): void
    {
        $this->prepare();
        $roleId = $this->ensureRole($role);
        $sql = $this->insertIgnoreSql('role_user', ['user_id', 'role_id']);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$this->normalizeUserId($userId), $roleId]);
        $this->forgetUserCache($userId);
    }

    public function revokeRole(string|int $userId, string $role): void
    {
        $this->prepare();
        $roleId = $this->roleId($role);
        if ($roleId === null) {
            return;
        }
        $stmt = $this->pdo()->prepare('DELETE FROM role_user WHERE user_id = ? AND role_id = ?');
        $stmt->execute([$this->normalizeUserId($userId), $roleId]);
        $this->forgetUserCache($userId);
    }

    public function grantPermissionToRole(string $role, string $permission): void
    {
        $this->prepare();
        $roleId = $this->ensureRole($role);
        $permId = $this->ensurePermission($permission);
        $sql = $this->insertIgnoreSql('permission_role', ['role_id', 'permission_id']);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$roleId, $permId]);
        $this->flushCache();
    }

    public function revokePermissionFromRole(string $role, string $permission): void
    {
        $this->prepare();
        $roleId = $this->roleId($role);
        $permId = $this->permissionId($permission);
        if ($roleId === null || $permId === null) {
            return;
        }
        $stmt = $this->pdo()->prepare('DELETE FROM permission_role WHERE role_id = ? AND permission_id = ?');
        $stmt->execute([$roleId, $permId]);
        $this->flushCache();
    }

    public function hasRole(mixed $user, string $role): bool
    {
        $userId = $this->extractUserId($user);
        if ($userId === null) {
            return false;
        }

        $roles = $this->rolesForUser($userId);
        return in_array($role, $roles, true);
    }

    public function hasPermission(mixed $user, string $permission): bool
    {
        $userId = $this->extractUserId($user);
        if ($userId === null) {
            return false;
        }

        $permissions = $this->permissionsForUser($userId);
        return in_array($permission, $permissions, true);
    }

    public function rolesForUser(string|int $userId): array
    {
        $cacheKey = 'rbac.roles.' . $this->normalizeUserId($userId);
        $cached = $this->cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $this->prepare();
        $sql = 'SELECT r.name FROM roles r JOIN role_user ur ON ur.role_id = r.id WHERE ur.user_id = ?';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$this->normalizeUserId($userId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $roles = array_map('strval', $rows);
        $this->cachePut($cacheKey, $roles);
        return $roles;
    }

    public function permissionsForUser(string|int $userId): array
    {
        $cacheKey = 'rbac.perms.' . $this->normalizeUserId($userId);
        $cached = $this->cacheGet($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $this->prepare();
        $sql = 'SELECT p.name FROM permissions p
            JOIN permission_role rp ON rp.permission_id = p.id
            JOIN role_user ur ON ur.role_id = rp.role_id
            WHERE ur.user_id = ?';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$this->normalizeUserId($userId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $perms = array_values(array_unique(array_map('strval', $rows)));
        $this->cachePut($cacheKey, $perms);
        return $perms;
    }

    private function ensureRole(string $role): int
    {
        $id = $this->roleId($role);
        if ($id !== null) {
            return $id;
        }
        $sql = $this->insertIgnoreSql('roles', ['name']);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$role]);
        $id = $this->roleId($role);
        return $id ?? (int) $this->pdo()->lastInsertId();
    }

    private function ensurePermission(string $permission): int
    {
        $id = $this->permissionId($permission);
        if ($id !== null) {
            return $id;
        }
        $sql = $this->insertIgnoreSql('permissions', ['name']);
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$permission]);
        $id = $this->permissionId($permission);
        return $id ?? (int) $this->pdo()->lastInsertId();
    }

    private function roleId(string $role): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM roles WHERE name = ?');
        $stmt->execute([$role]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function permissionId(string $permission): ?int
    {
        $stmt = $this->pdo()->prepare('SELECT id FROM permissions WHERE name = ?');
        $stmt->execute([$permission]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function pdo(): PDO
    {
        return $this->connections->connection();
    }

    private function insertIgnoreSql(string $table, array $columns): string
    {
        $driver = (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $cols = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        if ($driver === 'sqlite') {
            return 'INSERT OR IGNORE INTO ' . $table . ' (' . $cols . ') VALUES (' . $placeholders . ')';
        }
        if ($driver === 'pgsql') {
            return 'INSERT INTO ' . $table . ' (' . $cols . ') VALUES (' . $placeholders . ') ON CONFLICT DO NOTHING';
        }
        return 'INSERT IGNORE INTO ' . $table . ' (' . $cols . ') VALUES (' . $placeholders . ')';
    }

    private function prepare(): void
    {
        if ($this->autoMigrate) {
            $this->ensureSchema();
        }
    }

    private function cacheGet(string $key): mixed
    {
        if ($this->cache instanceof CacheManager) {
            return $this->cache->get($key);
        }
        return null;
    }

    private function cachePut(string $key, array $value): void
    {
        if ($this->cache instanceof CacheManager) {
            $this->cache->set($key, $value, $this->cacheTtl);
        }
    }

    private function forgetUserCache(string|int $userId): void
    {
        if ($this->cache instanceof CacheManager) {
            $id = $this->normalizeUserId($userId);
            $this->cache->delete('rbac.roles.' . $id);
            $this->cache->delete('rbac.perms.' . $id);
        }
    }

    private function flushCache(): void
    {
        // Cache invalidation is per-user; nothing global here.
    }

    private function extractUserId(mixed $user): string|int|null
    {
        if (is_int($user) || is_string($user)) {
            return $user;
        }
        if (is_object($user) && method_exists($user, 'getAuthIdentifier')) {
            return $user->getAuthIdentifier();
        }
        if (is_object($user) && property_exists($user, 'id')) {
            return $user->id;
        }
        if (is_array($user) && isset($user['id'])) {
            return $user['id'];
        }
        return null;
    }

    private function normalizeUserId(string|int $userId): string
    {
        return is_int($userId) ? (string) $userId : $userId;
    }

}
