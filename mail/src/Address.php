<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Mail;

final class Address
{
    public function __construct(
        public string $email,
        public ?string $name = null
    ) {
    }
}