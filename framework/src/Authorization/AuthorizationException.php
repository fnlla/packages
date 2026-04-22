<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Authorization;

use RuntimeException;

/**
 * @api
 */
final class AuthorizationException extends RuntimeException
{
    private int $status;

    public function __construct(string $message = 'Forbidden', int $status = 403)
    {
        parent::__construct($message, $status);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }
}

