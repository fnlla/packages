<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use RuntimeException;

final class FakeQueue implements QueueInterface
{
    /** @var JobInterface[] */
    private array $pushed = [];

    public function dispatch(JobInterface $job): void
    {
        $this->pushed[] = $job;
    }

    /** @return JobInterface[] */
    public function pushed(): array
    {
        return $this->pushed;
    }

    public function assertPushed(string|callable|null $condition = null): void
    {
        if ($condition === null) {
            if ($this->pushed === []) {
                throw new RuntimeException('Expected at least one job to be pushed.');
            }
            return;
        }

        if (is_string($condition)) {
            foreach ($this->pushed as $job) {
                if ($job instanceof $condition) {
                    return;
                }
            }
            throw new RuntimeException('Expected job of type ' . $condition . ' was not pushed.');
        }

        foreach ($this->pushed as $job) {
            if ($condition($job) === true) {
                return;
            }
        }

        throw new RuntimeException('Expected job matching predicate was not pushed.');
    }
}
