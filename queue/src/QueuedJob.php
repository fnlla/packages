<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use RuntimeException;

final class QueuedJob
{
    public function __construct(
        private string $id,
        private string $payload,
        private string $jobClass,
        private string $signature,
        private int $attempts,
        private int $maxAttempts
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function jobClass(): string
    {
        return $this->jobClass;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function job(): JobInterface
    {
        $allowed = $this->jobClass !== '' ? [$this->jobClass] : false;
        $job = @unserialize($this->payload, ['allowed_classes' => $allowed]);
        if ($job instanceof JobInterface && ($this->jobClass === '' || get_class($job) === $this->jobClass)) {
            return $job;
        }

        throw new RuntimeException('Unable to unserialize queued job payload.');
    }

    public function payload(): string
    {
        return $this->payload;
    }
}
