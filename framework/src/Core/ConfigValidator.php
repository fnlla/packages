<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

use RuntimeException;

/**
 * Configuration schema validator.
 *
 * @internal
 */
final class ConfigValidator
{
    public static function assertValid(array $config, array $schema): void
    {
        $errors = self::validate($config, $schema);
        if ($errors === []) {
            return;
        }

        $lines = ["Configuration validation failed:"];
        foreach ($errors as $error) {
            $lines[] = '- ' . $error;
        }

        throw new RuntimeException(implode("\n", $lines));
    }

    public static function validate(array $config, array $schema): array
    {
        $errors = [];

        foreach ($schema as $key => $ruleset) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $rules = self::normalizeRules($ruleset);
            if ($rules === []) {
                continue;
            }

            $value = self::getValue($config, $key);
            $exists = self::hasKey($config, $key);
            $nullable = in_array('nullable', $rules, true);
            $required = in_array('required', $rules, true);

            if (!$exists || self::isEmpty($value)) {
                if ($required && !$nullable) {
                    $errors[] = $key . ' is required.';
                }
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['required', 'nullable'], true)) {
                    continue;
                }

                [$name, $param] = self::parseRule($rule);

                if ($name === 'string' && !is_string($value)) {
                    $errors[] = $key . ' must be a string.';
                    continue;
                }

                if ($name === 'bool' && !is_bool($value)) {
                    $errors[] = $key . ' must be a boolean.';
                    continue;
                }

                if ($name === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[] = $key . ' must be an integer.';
                    continue;
                }

                if ($name === 'numeric' && !is_numeric($value)) {
                    $errors[] = $key . ' must be numeric.';
                    continue;
                }

                if ($name === 'array' && !is_array($value)) {
                    $errors[] = $key . ' must be an array.';
                    continue;
                }

                if ($name === 'in' && $param !== null) {
                    $allowed = array_map('trim', explode(',', $param));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[] = $key . ' must be one of: ' . implode(', ', $allowed) . '.';
                    }
                }
            }
        }

        return $errors;
    }

    private static function normalizeRules(string|array $ruleSet): array
    {
        if (is_array($ruleSet)) {
            return $ruleSet;
        }
        return array_filter(array_map('trim', explode('|', $ruleSet)), static fn ($rule) => $rule !== '');
    }

    private static function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        return [strtolower($parts[0]), $parts[1] ?? null];
    }

    private static function hasKey(array $config, string $key): bool
    {
        if (!str_contains($key, '.')) {
            return array_key_exists($key, $config);
        }

        $current = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }

    private static function getValue(array $config, string $key): mixed
    {
        if (!str_contains($key, '.')) {
            return $config[$key] ?? null;
        }

        $current = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private static function isEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if ($value === '') {
            return true;
        }
        if (is_array($value) && $value === []) {
            return true;
        }
        return false;
    }
}



