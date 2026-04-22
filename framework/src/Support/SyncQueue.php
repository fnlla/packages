<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Queue\JobInterface;
use Fnlla\Contracts\Queue\QueueInterface;
use Fnlla\Core\Container;
use RuntimeException;
use Throwable;

class SyncQueue implements QueueInterface
{
    public function __construct(private ?Container $container = null)
    {
    }

    public function push(callable|string $job, array $payload = []): mixed
    {
        $resolved = $this->resolveJob($job);

        if (is_callable($resolved)) {
            return $resolved($payload);
        }

        if ($resolved instanceof JobInterface) {
            return $resolved->handle($payload);
        }

        if (is_object($resolved) && method_exists($resolved, 'handle')) {
            return $resolved->handle($payload);
        }

        throw new RuntimeException('Invalid job provided to queue.');
    }

    private function resolveJob(callable|string $job): mixed
    {
        if (is_string($job) && class_exists($job)) {
            return $this->resolveFromContainer($job) ?? new $job();
        }

        return $job;
    }

    private function resolveFromContainer(string $className): mixed
    {
        if ($this->container === null) {
            return null;
        }

        try {
            return $this->container->make($className);
        } catch (Throwable $e) {
            return null;
        }
    }
}





