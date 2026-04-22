<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Runtime;

use Fnlla\Contracts\Http\KernelInterface;

/**
 * @api
 */
interface RuntimeInterface
{
    public function run(KernelInterface $kernel): void;
}






