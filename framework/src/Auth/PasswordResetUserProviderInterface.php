<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

interface PasswordResetUserProviderInterface
{
    public function updatePassword(mixed $user, string $passwordHash): void;
}
