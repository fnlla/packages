<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Authorization;

use Fnlla\Core\Container;
use Fnlla\Http\Request;
use Throwable;
use ReflectionMethod;

/**
 * @api
 */
final class Gate
{
    /** @var array<string, callable> */
    private array $abilities = [];

    public function __construct(private Container $app, private PolicyRegistry $registry)
    {
    }

    public function define(string $ability, callable $callback): void
    {
        $ability = trim($ability);
        if ($ability === '') {
            return;
        }
        $this->abilities[$ability] = $callback;
    }

    public function policy(string $modelClass, string $policyClass): void
    {
        $this->registry->register($modelClass, $policyClass);
    }

    public function allows(string $ability, mixed $target = null, ?Request $request = null, mixed $user = null): bool
    {
        $user = $user ?? $this->resolveUser($request);

        if (isset($this->abilities[$ability])) {
            return (bool) ($this->abilities[$ability])($user, $target, $request);
        }

        $policyClass = $this->registry->resolvePolicyFor($target);
        if ($policyClass === null) {
            return false;
        }

        $policy = $this->resolvePolicy($policyClass);
        if ($policy === null) {
            return false;
        }

        if (method_exists($policy, 'before')) {
            $before = $policy->before($user, $ability, $target, $request);
            if ($before !== null) {
                return (bool) $before;
            }
        }

        if (!method_exists($policy, $ability)) {
            return false;
        }

        return $this->callPolicy($policy, $ability, $user, $target, $request);
    }

    public function authorize(string $ability, mixed $target = null, ?Request $request = null, mixed $user = null): void
    {
        if (!$this->allows($ability, $target, $request, $user)) {
            throw new AuthorizationException('Forbidden', 403);
        }
    }

    private function resolveUser(?Request $request): mixed
    {
        if (class_exists(\Fnlla\Auth\AuthManager::class) && $this->app->has(\Fnlla\Auth\AuthManager::class)) {
            try {
                $auth = $this->app->make(\Fnlla\Auth\AuthManager::class);
                if ($auth instanceof \Fnlla\Auth\AuthManager) {
                    return $auth->user($request);
                }
            } catch (Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function resolvePolicy(string $policyClass): ?object
    {
        if (!class_exists($policyClass)) {
            return null;
        }

        try {
            $instance = $this->app->make($policyClass);
            return is_object($instance) ? $instance : null;
        } catch (Throwable $e) {
            try {
                return new $policyClass();
            } catch (Throwable $inner) {
                return null;
            }
        }
    }

    private function callPolicy(object $policy, string $ability, mixed $user, mixed $target, ?Request $request): bool
    {
        try {
            $method = new ReflectionMethod($policy, $ability);
            $params = $method->getNumberOfParameters();
        } catch (Throwable $e) {
            return false;
        }

        try {
            if ($params <= 1) {
                return (bool) $policy->{$ability}($user);
            }
            if ($params === 2) {
                return (bool) $policy->{$ability}($user, $target);
            }
            return (bool) $policy->{$ability}($user, $target, $request);
        } catch (Throwable $e) {
            return false;
        }
    }
}

