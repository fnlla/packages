<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use RuntimeException;

/**
 * Validation exception carrying error details.
 *
 * @api
 */
final class ValidationException extends RuntimeException
{
    private int $status;
    private array $errors;
    private array $oldInput;
    private string $bag;

    public function __construct(
        array $errors,
        string $message = 'Validation failed.',
        int $status = 422,
        array $oldInput = [],
        string $bag = 'default'
    ) {
        parent::__construct($message);
        $this->status = $status;
        $this->errors = $errors;
        $this->oldInput = $oldInput;
        $this->bag = $bag;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function oldInput(): array
    {
        return $this->oldInput;
    }

    public function bag(): string
    {
        return $this->bag;
    }
}



