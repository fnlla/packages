<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Simple error bag for form validation messages.
 *
 * @api
 *
 * @implements IteratorAggregate<string, array<int, string>|string>
 */
final class ErrorBag implements Countable, IteratorAggregate
{
    public function __construct(
        /** @var array<string, array<int, string>|string> */
        private array $errors = [],
        private string $bag = 'default'
    ) {
    }

    public function bag(): string
    {
        return $this->bag;
    }

    public function has(string $key): bool
    {
        return isset($this->errors[$key]) && $this->errors[$key] !== [];
    }

    public function first(string $key, string $default = ''): string
    {
        if (!$this->has($key)) {
            return $default;
        }

        $messages = $this->errors[$key];
        if (is_array($messages)) {
            return (string) ($messages[0] ?? $default);
        }

        return (string) $messages;
    }

    public function get(string $key, array $default = []): array
    {
        $messages = $this->errors[$key] ?? $default;
        return is_array($messages) ? $messages : [$messages];
    }

    public function all(): array
    {
        $all = [];
        foreach ($this->errors as $messages) {
            if (is_array($messages)) {
                foreach ($messages as $msg) {
                    $all[] = (string) $msg;
                }
                continue;
            }
            $all[] = (string) $messages;
        }
        return $all;
    }

    public function toArray(): array
    {
        return $this->errors;
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->errors);
    }
}
