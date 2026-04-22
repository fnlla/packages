<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

final class AuthResult
{
    public function __construct(
        public bool $authenticated,
        public mixed $user,
        public ?string $rememberToken
    ) {
    }
}
