<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Throwable;

interface QueueDriverInterface extends QueueInterface
{
    public function pop(?int $retryAfter = null): ?QueuedJob;

    public function delete(int|string $id): void;

    public function release(int|string $id, int $delaySeconds = 0): void;

    public function fail(int|string $id, string $payload, ?Throwable $error = null): void;

    public function retryAfter(): int;

    public function validate(QueuedJob $job): ?string;
}
