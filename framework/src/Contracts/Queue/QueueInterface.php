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
interface QueueInterface
{
    public function push(callable|string $job, array $payload = []): mixed;
}




