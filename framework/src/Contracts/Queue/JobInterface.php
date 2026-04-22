<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Queue;

/**
 * @api
 */
interface JobInterface
{
    public function handle(array $payload = []): mixed;
}




