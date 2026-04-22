<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Core\Container;

final class SyncQueue implements QueueInterface
{
    public function __construct(private Container $app)
    {
    }

    public function dispatch(JobInterface $job): void
    {
        $job->handle($this->app);
    }
}