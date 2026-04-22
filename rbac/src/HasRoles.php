<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Rbac;

use Fnlla\Authorization\Gate;

trait HasRoles
{
    public function hasRole(string $role): bool
    {
        $rbac = $this->resolveRbac();
        if (!$rbac instanceof RbacManager) {
            return false;
        }

        return $rbac->hasRole($this, $role);
    }

    public function can(string $permission): bool
    {
        if (!function_exists('app')) {
            return false;
        }

        $app = app();
        if (!$app instanceof \Fnlla\Core\Container) {
            return false;
        }

        if (!$app->has(Gate::class)) {
            return false;
        }

        $gate = $app->make(Gate::class);
        if (!$gate instanceof Gate) {
            return false;
        }

        return $gate->allows('permission', $permission, null, $this);
    }

    private function resolveRbac(): ?RbacManager
    {
        if (!function_exists('app')) {
            return null;
        }

        $app = app();
        if (!$app instanceof \Fnlla\Core\Container) {
            return null;
        }

        if (!$app->has(RbacManager::class)) {
            return null;
        }

        $rbac = $app->make(RbacManager::class);
        return $rbac instanceof RbacManager ? $rbac : null;
    }
}
