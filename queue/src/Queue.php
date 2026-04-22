<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Queue;

use Fnlla\Core\Container;
use RuntimeException;
use Throwable;

final class Queue
{
    private static ?FakeQueue $fake = null;

    public static function fake(): FakeQueue
    {
        $fake = new FakeQueue();
        self::$fake = $fake;

        $app = self::app();
        if ($app instanceof Container) {
            $app->instance(QueueInterface::class, $fake);

            if ($app->has(QueueManager::class)) {
                $manager = $app->make(QueueManager::class);
                if ($manager instanceof QueueManager) {
                    $manager->setQueue($fake);
                }
            }
        }

        return $fake;
    }

    public static function assertPushed(string|callable|null $condition = null): void
    {
        self::fakeOrFail()->assertPushed($condition);
    }

    /** @return JobInterface[] */
    public static function pushed(): array
    {
        return self::fakeOrFail()->pushed();
    }

    public static function dispatch(JobInterface $job): void
    {
        if (self::$fake instanceof FakeQueue) {
            self::$fake->dispatch($job);
            return;
        }

        $app = self::app();
        if ($app instanceof Container && $app->has(QueueManager::class)) {
            $manager = $app->make(QueueManager::class);
            if ($manager instanceof QueueManager) {
                $manager->dispatch($job);
                return;
            }
        }

        throw new RuntimeException('QueueManager is not available. Ensure fnlla/queue is installed and the provider is registered.');
    }

    private static function app(): ?Container
    {
        if (!function_exists('app')) {
            return null;
        }

        try {
            $app = app();
        } catch (Throwable) {
            return null;
        }

        return $app;
    }

    private static function fakeOrFail(): FakeQueue
    {
        if (!self::$fake instanceof FakeQueue) {
            throw new RuntimeException('Queue is not faked. Call Queue::fake() before asserting.');
        }

        return self::$fake;
    }
}
