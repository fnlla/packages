<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

interface RegistrationUserProviderInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function createUser(array $data): mixed;
}
