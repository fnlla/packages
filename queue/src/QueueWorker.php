<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Core\Container;
use Throwable;
use RuntimeException;

final class QueueWorker
{
    private ?int $maxAttemptsOverride;
    private int $retryAfter;
    private array $backoff;

    public function __construct(
        private QueueDriverInterface $queue,
        private Container $app,
        ?int $maxAttempts = null,
        array $backoff = [],
        ?int $retryAfter = null
    )
    {
        $this->maxAttemptsOverride = $maxAttempts !== null && $maxAttempts > 0
            ? $maxAttempts
            : null;
        $this->retryAfter = $retryAfter !== null && $retryAfter > 0
            ? $retryAfter
            : $queue->retryAfter();
        $this->backoff = $this->normalizeBackoff($backoff);
    }

    public function work(int $maxJobs = 0, int $sleepSeconds = 1): int
    {
        $processed = 0;

        while ($maxJobs === 0 || $processed < $maxJobs) {
            $job = $this->queue->pop($this->retryAfter);
            if ($job === null) {
                if ($maxJobs !== 0) {
                    break;
                }
                if ($sleepSeconds > 0) {
                    sleep($sleepSeconds);
                }
                continue;
            }

            try {
                $validationError = $this->queue->validate($job);
                if ($validationError !== null) {
                    $this->queue->fail($job->id(), $job->payload(), new RuntimeException($validationError));
                    $processed++;
                    continue;
                }
                $instance = $job->job();
                $instance->handle($this->app);
                $this->queue->delete($job->id());
            } catch (Throwable $e) {
                $maxAttempts = $this->maxAttemptsOverride ?? $job->maxAttempts();
                if ($job->attempts() >= $maxAttempts) {
                    $this->queue->fail($job->id(), $job->payload(), $e);
                } else {
                    $delay = $this->computeBackoff($job->attempts());
                    $this->queue->release($job->id(), $delay);
                }
            }

            $processed++;
        }

        return $processed;
    }

    private function normalizeBackoff(array $backoff): array
    {
        $values = [];
        foreach ($backoff as $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $int = (int) $value;
            if ($int >= 0) {
                $values[] = $int;
            }
        }
        return $values;
    }

    private function computeBackoff(int $attempts): int
    {
        if ($this->backoff === []) {
            return 0;
        }

        $index = max(0, $attempts - 1);
        if ($index >= count($this->backoff)) {
            return (int) $this->backoff[count($this->backoff) - 1];
        }

        return (int) $this->backoff[$index];
    }
}
