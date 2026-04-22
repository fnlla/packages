<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Http\UploadedFile;

final class Validator
{
    private array $errors = [];
    private array $validated = [];
    private array $messages = [];
    private static array $extensions = [];
    private static array $extensionMessages = [];

    public function __construct(private array $data, private array $rules, array $messages = [])
    {
        $this->messages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public static function extend(string $name, callable $callback, ?string $message = null): void
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return;
        }
        self::$extensions[$name] = $callback;
        if ($message !== null) {
            self::$extensionMessages[$name] = $message;
        }
    }

    public function passes(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = $this->normalizeRules($ruleSet);
            $targets = $this->resolveTargets((string) $field);
            if ($targets === []) {
                $targets = [[
                    'path' => (string) $field,
                    'value' => null,
                    'exists' => false,
                ]];
            }

            foreach ($targets as $target) {
                $path = $target['path'];
                $value = $target['value'];
                $valueExists = $target['exists'];

                if (in_array('sometimes', $rules, true) && !$valueExists) {
                    continue;
                }

                $nullable = in_array('nullable', $rules, true);
                $required = in_array('required', $rules, true);

                if ($this->isEmpty($value)) {
                    if ($required && !$valueExists) {
                        $this->addError($path, $this->message($field, 'required', 'The ' . $path . ' field is required.'));
                    } elseif ($required) {
                        $this->addError($path, $this->message($field, 'required', 'The ' . $path . ' field is required.'));
                    }
                    if (!$required || $nullable) {
                        continue;
                    }
                }

                foreach ($rules as $rule) {
                    if ($rule === 'required' || $rule === 'nullable') {
                        continue;
                    }

                    if (!is_string($rule) && is_callable($rule)) {
                        $result = $rule($value, $path, $this->data);
                        if ($result === true) {
                            continue;
                        }
                        $message = is_string($result) ? $result : 'The ' . $path . ' field is invalid.';
                        $this->addError($path, $message);
                        continue;
                    }

                    [$name, $param] = $this->parseRule($rule);

                    if ($name === 'sometimes') {
                        continue;
                    }

                    if (isset(self::$extensions[$name])) {
                        $callback = self::$extensions[$name];
                        $result = $callback($value, $path, $this->data, $param);
                        if ($result === true) {
                            continue;
                        }
                        $message = self::$extensionMessages[$name] ?? 'The ' . $path . ' field is invalid.';
                        $this->addError($path, $this->message($field, $name, $message));
                        continue;
                    }

                    if ($name === 'string' && !is_string($value)) {
                        $this->addError($path, $this->message($field, 'string', 'The ' . $path . ' must be a string.'));
                        continue;
                    }

                    if ($name === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $this->addError($path, $this->message($field, 'integer', 'The ' . $path . ' must be an integer.'));
                        continue;
                    }

                    if ($name === 'numeric' && !is_numeric($value)) {
                        $this->addError($path, $this->message($field, 'numeric', 'The ' . $path . ' must be numeric.'));
                        continue;
                    }

                    if ($name === 'boolean' && filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                        $this->addError($path, $this->message($field, 'boolean', 'The ' . $path . ' must be boolean.'));
                        continue;
                    }

                    if ($name === 'date' && $this->validateDate($value, $param) === false) {
                        $this->addError($path, $this->message($field, 'date', 'The ' . $path . ' must be a valid date.'));
                        continue;
                    }

                    if ($name === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                        $this->addError($path, $this->message($field, 'email', 'The ' . $path . ' must be a valid email.'));
                        continue;
                    }

                    if ($name === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                        $this->addError($path, $this->message($field, 'url', 'The ' . $path . ' must be a valid URL.'));
                        continue;
                    }

                    if ($name === 'uuid' && !$this->validateUuid($value)) {
                        $this->addError($path, $this->message($field, 'uuid', 'The ' . $path . ' must be a valid UUID.'));
                        continue;
                    }

                    if ($name === 'array' && !is_array($value)) {
                        $this->addError($path, $this->message($field, 'array', 'The ' . $path . ' must be an array.'));
                        continue;
                    }

                    if ($name === 'list' && !$this->validateList($value)) {
                        $this->addError($path, $this->message($field, 'list', 'The ' . $path . ' must be a list.'));
                        continue;
                    }

                    if ($name === 'file' && !$value instanceof UploadedFile) {
                        $this->addError($path, $this->message($field, 'file', 'The ' . $path . ' must be a valid file.'));
                        continue;
                    }

                    if ($name === 'min' && $param !== null) {
                        if (!$this->validateMin($value, $param)) {
                            $this->addError($path, $this->message($field, 'min', 'The ' . $path . ' must be at least ' . $param . '.'));
                        }
                        continue;
                    }

                    if ($name === 'max' && $param !== null) {
                        if (!$this->validateMax($value, $param)) {
                            $this->addError($path, $this->message($field, 'max', 'The ' . $path . ' may not be greater than ' . $param . '.'));
                        }
                        continue;
                    }

                    if ($name === 'in' && $param !== null) {
                        $allowed = array_map('trim', explode(',', $param));
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->addError($path, $this->message($field, 'in', 'The ' . $path . ' field must be one of: ' . implode(', ', $allowed) . '.'));
                        }
                        continue;
                    }

                    if ($name === 'regex' && $param !== null) {
                        if (!$this->validateRegex($value, $param)) {
                            $this->addError($path, $this->message($field, 'regex', 'The ' . $path . ' format is invalid.'));
                        }
                        continue;
                    }

                    if ($name === 'confirmed') {
                        if (!$this->validateConfirmed($path, $value)) {
                            $this->addError($path, $this->message($field, 'confirmed', 'The ' . $path . ' confirmation does not match.'));
                        }
                        continue;
                    }

                    if ($name === 'mimes' && $param !== null) {
                        if (!$this->validateMimes($value, $param)) {
                            $this->addError($path, $this->message($field, 'mimes', 'The ' . $path . ' must be a valid file type.'));
                        }
                        continue;
                    }

                    if ($name === 'size' && $param !== null) {
                        if (!$this->validateSize($value, $param)) {
                            $this->addError($path, $this->message($field, 'size', 'The ' . $path . ' size is invalid.'));
                        }
                        continue;
                    }
                }

                if (!isset($this->errors[$path]) && $valueExists) {
                    $this->validated[$path] = $value;
                }
            }
        }

        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function normalizeRules(string|array $ruleSet): array
    {
        if (is_array($ruleSet)) {
            return $ruleSet;
        }
        return array_filter(array_map('trim', explode('|', $ruleSet)), static fn ($rule) => $rule !== '');
    }

    private function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        return [strtolower($parts[0]), $parts[1] ?? null];
    }

    private function isEmpty(mixed $value): bool
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

    private function resolveTargets(string $field): array
    {
        if (!str_contains($field, '*')) {
            [$value, $exists] = $this->getByPath($this->data, $field);
            return [[
                'path' => $field,
                'value' => $value,
                'exists' => $exists,
            ]];
        }

        $segments = explode('.', $field);
        return $this->walkTargets($this->data, $segments, '');
    }

    private function walkTargets(mixed $data, array $segments, string $path): array
    {
        if ($segments === []) {
            return [[
                'path' => $path,
                'value' => $data,
                'exists' => true,
            ]];
        }

        $segment = array_shift($segments);
        if ($segment === '*') {
            if (!is_array($data)) {
                return [];
            }
            $results = [];
            foreach ($data as $key => $value) {
                $nextPath = $path === '' ? (string) $key : $path . '.' . $key;
                $results = array_merge($results, $this->walkTargets($value, $segments, $nextPath));
            }
            return $results;
        }

        if (is_array($data) && array_key_exists($segment, $data)) {
            $nextPath = $path === '' ? $segment : $path . '.' . $segment;
            return $this->walkTargets($data[$segment], $segments, $nextPath);
        }

        return [];
    }

    private function getByPath(array $data, string $path): array
    {
        $segments = $path === '' ? [] : explode('.', $path);
        $current = $data;
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return [null, false];
            }
            $current = $current[$segment];
        }

        return [$current, true];
    }

    private function message(string $field, string $rule, string $default): string
    {
        $key = $field . '.' . $rule;
        if (isset($this->messages[$key])) {
            return (string) $this->messages[$key];
        }
        if (isset($this->messages[$rule])) {
            return (string) $this->messages[$rule];
        }
        return $default;
    }

    private function validateMin(mixed $value, string $param): bool
    {
        $size = $this->sizeOf($value);
        if ($size === null) {
            return false;
        }
        return $size >= (float) $param;
    }

    private function validateMax(mixed $value, string $param): bool
    {
        $size = $this->sizeOf($value);
        if ($size === null) {
            return false;
        }
        return $size <= (float) $param;
    }

    private function sizeOf(mixed $value): float|int|null
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        }
        if ($value instanceof UploadedFile) {
            return (float) ($value->getSize() ?? 0);
        }
        if (is_array($value)) {
            return count($value);
        }
        return null;
    }

    private function validateDate(mixed $value, ?string $format = null): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }
        $value = (string) $value;
        if ($format !== null && $format !== '') {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt === false) {
                return false;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                return false;
            }
            return $dt->format($format) === $value;
        }
        return strtotime($value) !== false;
    }

    private function validateUuid(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    private function validateRegex(mixed $value, string $pattern): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $result = @preg_match($pattern, $value);
        return $result === 1;
    }

    private function validateMimes(mixed $value, string $param): bool
    {
        if (!$value instanceof UploadedFile) {
            return false;
        }
        $allowed = array_filter(array_map('trim', explode(',', $param)), static fn ($item) => $item !== '');
        if ($allowed === []) {
            return false;
        }

        $mimeAllowed = [];
        $extAllowed = [];
        foreach ($allowed as $item) {
            if (str_contains($item, '/')) {
                $mimeAllowed[] = strtolower($item);
            } else {
                $extAllowed[] = strtolower(ltrim($item, '.'));
            }
        }

        $mime = strtolower((string) ($value->getClientMediaType() ?? ''));
        if ($mime !== '' && $mimeAllowed !== [] && in_array($mime, $mimeAllowed, true)) {
            return true;
        }

        $ext = strtolower($value->extension());
        if ($ext !== '' && $extAllowed !== [] && in_array($ext, $extAllowed, true)) {
            return true;
        }

        return false;
    }

    private function validateSize(mixed $value, string $param): bool
    {
        $size = $this->sizeOf($value);
        if ($size === null) {
            return false;
        }
        return $size <= (float) $param;
    }

    private function validateConfirmed(string $path, mixed $value): bool
    {
        if (is_array($value)) {
            return false;
        }

        $segments = explode('.', $path);
        if ($segments === []) {
            return false;
        }
        $last = array_pop($segments);
        $segments[] = $last . '_confirmation';
        $confirmationPath = implode('.', $segments);

        [$confirmValue, $exists] = $this->getByPath($this->data, $confirmationPath);
        if (!$exists) {
            return false;
        }

        return (string) $confirmValue === (string) $value;
    }

    private function validateList(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}



