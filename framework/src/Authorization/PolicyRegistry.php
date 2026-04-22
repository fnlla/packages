<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Authorization;

/**
 * @api
 */
final class PolicyRegistry
{
    /** @var array<string, string> */
    private array $policies = [];
    private bool $guessEnabled = true;

    /**
     * @param array<string, string> $policies
     */
    public function __construct(array $policies = [], bool $guessEnabled = true)
    {
        $this->registerMany($policies);
        $this->guessEnabled = $guessEnabled;
    }

    public function register(string $modelClass, string $policyClass): void
    {
        if ($modelClass === '' || $policyClass === '') {
            return;
        }
        $this->policies[$modelClass] = $policyClass;
    }

    /**
     * @param array<string, string> $policies
     */
    public function registerMany(array $policies): void
    {
        foreach ($policies as $model => $policy) {
            if (is_string($model) && is_string($policy)) {
                $this->register($model, $policy);
            }
        }
    }

    public function resolvePolicyFor(mixed $target): ?string
    {
        $class = $this->normalizeTargetClass($target);
        if ($class === null) {
            return null;
        }

        if (isset($this->policies[$class])) {
            return $this->policies[$class];
        }

        if ($this->guessEnabled) {
            $guessed = $this->guessPolicyClass($class);
            if ($guessed !== null) {
                $this->policies[$class] = $guessed;
                return $guessed;
            }
        }

        return null;
    }

    public function enableGuessing(bool $enabled): void
    {
        $this->guessEnabled = $enabled;
    }

    private function normalizeTargetClass(mixed $target): ?string
    {
        if (is_object($target)) {
            return get_class($target);
        }

        if (is_string($target) && class_exists($target)) {
            return $target;
        }

        return null;
    }

    private function guessPolicyClass(string $modelClass): ?string
    {
        $base = $this->classBasename($modelClass);

        $candidates = [];

        if (str_contains($modelClass, '\\Models\\')) {
            $candidates[] = str_replace('\\Models\\', '\\Policies\\', $modelClass) . 'Policy';
        }

        $namespace = $this->classNamespace($modelClass);
        if ($namespace !== '') {
            $candidates[] = $namespace . '\\Policies\\' . $base . 'Policy';
        }

        $candidates[] = 'App\\Policies\\' . $base . 'Policy';

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function classBasename(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function classNamespace(string $class): string
    {
        $pos = strrpos($class, '\\');
        return $pos === false ? '' : substr($class, 0, $pos);
    }
}

