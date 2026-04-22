<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

use Closure;
use Fnlla\Core\ContainerException;
use Fnlla\Runtime\Resetter;
use Fnlla\Core\NotFoundException;
use Fnlla\Support\Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;
use Throwable;

/**
 * Dependency injection container for binding and resolving services.
 *
 * @api
 */
class Container implements ContainerInterface, Resetter
{
    public const SCOPE_TRANSIENT = 'transient';
    public const SCOPE_SINGLETON = 'singleton';
    public const SCOPE_SCOPED = 'scoped';

    private array $bindings = [];
    private array $instances = [];
    private array $scopedInstances = [];
    private array $buildPlans = [];
    private array $instantiableCache = [];
    /** @var Resetter[] */
    private array $resetters = [];

    public function bind(string $abstract, callable|string|null $concrete = null, bool|string $scope = false): void
    {
        if (is_bool($scope)) {
            $scope = $scope ? self::SCOPE_SINGLETON : self::SCOPE_TRANSIENT;
        }

        if (!in_array($scope, [self::SCOPE_TRANSIENT, self::SCOPE_SINGLETON, self::SCOPE_SCOPED], true)) {
            $scope = self::SCOPE_TRANSIENT;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'scope' => $scope,
        ];
    }

    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, self::SCOPE_SINGLETON);
    }

    public function scoped(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, self::SCOPE_SCOPED);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function scopedInstance(string $abstract, mixed $instance): void
    {
        $this->scopedInstances[$abstract] = $instance;
    }

    public function registerResetter(Resetter $resetter): void
    {
        foreach ($this->resetters as $existing) {
            if ($existing === $resetter) {
                return;
            }
        }
        $this->resetters[] = $resetter;
    }

    /**
     * @return Resetter[]
     */
    public function resetters(): array
    {
        return $this->resetters;
    }

    public function has(string $abstract): bool
    {
        return array_key_exists($abstract, $this->instances)
            || array_key_exists($abstract, $this->scopedInstances)
            || array_key_exists($abstract, $this->bindings);
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function config(): ConfigRepository
    {
        $config = $this->make(ConfigRepository::class);
        if ($config instanceof ConfigRepository) {
            return $config;
        }

        throw new RuntimeException('Config service is not available.');
    }

    public function configRepository(): ConfigRepository
    {
        return $this->config();
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (array_key_exists($abstract, $this->scopedInstances)) {
            return $this->scopedInstances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;

        $object = $this->build($concrete, $parameters, $abstract);

        $scope = $binding['scope'] ?? self::SCOPE_TRANSIENT;
        if ($scope === self::SCOPE_SINGLETON) {
            $this->instances[$abstract] = $object;
        } elseif ($scope === self::SCOPE_SCOPED) {
            $this->scopedInstances[$abstract] = $object;
        }

        return $object;
    }

    public function call(callable $callable, array $parameters = []): mixed
    {
        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction(Closure::fromCallable($callable));

        $args = $this->resolveParameters($reflection->getParameters(), $parameters);

        return $callable(...$args);
    }

    private function build(callable|string $concrete, array $parameters, string $abstract): mixed
    {
        if (is_callable($concrete) && !is_string($concrete)) {
            try {
                return $concrete($this, $parameters);
            } catch (Throwable $e) {
                throw new ContainerException('Unable to resolve binding for [' . $abstract . '].', 0, $e);
            }
        }

        if (!is_string($concrete) || !class_exists($concrete)) {
            throw new NotFoundException('Unable to resolve binding for [' . (string) $concrete . '].');
        }

        $plan = $this->getBuildPlan($concrete);
        if (!$plan['instantiable']) {
            throw new ContainerException('Class [' . $concrete . '] is not instantiable.');
        }

        if ($plan['constructor'] === null) {
            return new $concrete();
        }

        $args = $this->resolveParametersFromPlan($plan['parameters'], $parameters);
        try {
            return new $concrete(...$args);
        } catch (Throwable $e) {
            throw new ContainerException('Unable to instantiate [' . $concrete . '].', 0, $e);
        }
    }

    private function resolveParametersFromPlan(array $parameters, array $overrides): array
    {
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter['name'];

            if (array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];
                continue;
            }

            $typeName = $parameter['type'];
            if ($typeName !== null && !$parameter['builtin']) {
                if ($parameter['hasDefault'] && !$this->has($typeName)) {
                    $isInterface = interface_exists($typeName);
                    $isAbstract = !$isInterface && !$this->isInstantiableClass($typeName);
                    if ($isInterface || $isAbstract) {
                        $args[] = $parameter['default'];
                        continue;
                    }
                }

                $args[] = $this->make($typeName);
                continue;
            }

            if ($parameter['hasDefault']) {
                $args[] = $parameter['default'];
                continue;
            }

            throw new ContainerException('Unresolvable dependency [' . $name . '].');
        }

        return $args;
    }

    private function resolveParameters(array $parameters, array $overrides): array
    {
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($parameter->isDefaultValueAvailable() && !$this->has($className)) {
                    $isInterface = interface_exists($className);
                    $isAbstract = !$isInterface && !$this->isInstantiableClass($className);
                    if ($isInterface || $isAbstract) {
                        $args[] = $parameter->getDefaultValue();
                        continue;
                    }
                }

                $args[] = $this->make($className);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException('Unresolvable dependency [' . $name . '].');
        }

        return $args;
    }

    private function getBuildPlan(string $className): array
    {
        if (isset($this->buildPlans[$className])) {
            return $this->buildPlans[$className];
        }

        $reflection = new ReflectionClass($className);
        $instantiable = $reflection->isInstantiable();
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $this->buildPlans[$className] = [
                'instantiable' => $instantiable,
                'constructor' => null,
                'parameters' => [],
            ];
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $typeName = null;
            $builtin = true;
            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                $builtin = $type->isBuiltin();
            }

            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $typeName,
                'builtin' => $builtin,
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $this->buildPlans[$className] = [
            'instantiable' => $instantiable,
            'constructor' => $constructor,
            'parameters' => $parameters,
        ];
    }

    private function isInstantiableClass(string $className): bool
    {
        if (isset($this->instantiableCache[$className])) {
            return $this->instantiableCache[$className];
        }

        if (!class_exists($className)) {
            return $this->instantiableCache[$className] = false;
        }

        $reflection = new ReflectionClass($className);
        return $this->instantiableCache[$className] = $reflection->isInstantiable();
    }

    public function reset(): void
    {
        $this->scopedInstances = [];
    }
}







