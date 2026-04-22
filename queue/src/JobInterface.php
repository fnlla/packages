<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Core\Container;

interface JobInterface
{
    public function handle(Container $app): void;
}