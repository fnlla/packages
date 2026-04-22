<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

interface QueueInterface
{
    public function dispatch(JobInterface $job): void;
}