<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Events\EventDispatcherInterface;
use Fnlla\Core\Container;
use RuntimeException;
use Throwable;

final class EventDispatcher implements EventDispatcherInterface
{
    private array $listeners = [];
    private array $wildcards = [];
    /** @var callable|null */
    private $afterCommitHandler = null;

    public function __construct(private ?Container $container = null)
    {
    }

    public function listen(string $event, callable|string $listener): void
    {
        if (str_contains($event, '*')) {
            $this->wildcards[] = ['pattern' => $event, 'listener' => $listener];
            return;
        }

        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object|string $event, array $payload = []): array
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $listeners = $this->listeners[$eventName] ?? [];
        if ($this->wildcards !== []) {
            foreach ($this->wildcards as $entry) {
                $pattern = (string) ($entry['pattern'] ?? '');
                if ($pattern !== '' && $this->matchesWildcard($pattern, $eventName)) {
                    $listeners[] = $entry['listener'];
                }
            }
        }
        $responses = [];

        foreach ($listeners as $listener) {
            $callable = $this->resolveListener($listener);
            if (is_callable($callable)) {
                $responses[] = $this->invokeListener($callable, $event, $payload);
                continue;
            }

            if (is_object($callable) && method_exists($callable, 'handle')) {
                $responses[] = $this->invokeListener([$callable, 'handle'], $event, $payload);
                continue;
            }

            throw new RuntimeException('Invalid event listener.');
        }

        return $responses;
    }

    public function dispatchAfterCommit(object|string $event, array $payload = []): void
    {
        if (is_callable($this->afterCommitHandler)) {
            ($this->afterCommitHandler)(function () use ($event, $payload): void {
                $this->dispatch($event, $payload);
            });
            return;
        }

        $this->dispatch($event, $payload);
    }

    public function setAfterCommitHandler(callable $handler): void
    {
        $this->afterCommitHandler = $handler;
    }

    private function resolveListener(callable|string $listener): mixed
    {
        if (is_string($listener) && str_contains($listener, '@')) {
            [$class, $method] = explode('@', $listener, 2);
            if ($class !== '' && $method !== '' && class_exists($class)) {
                $instance = $this->resolveFromContainer($class) ?? new $class();
                return [$instance, $method];
            }
        }

        if (is_string($listener) && class_exists($listener)) {
            return $this->resolveFromContainer($listener) ?? new $listener();
        }

        if (is_array($listener) && count($listener) === 2 && is_string($listener[0]) && class_exists($listener[0])) {
            $instance = $this->resolveFromContainer($listener[0]);
            if ($instance !== null) {
                return [$instance, $listener[1]];
            }
        }

        return $listener;
    }

    private function invokeListener(callable $callable, object|string $event, array $payload): mixed
    {
        if ($this->container instanceof Container) {
            return $this->container->call($callable, [
                'event' => $event,
                'payload' => $payload,
            ]);
        }

        try {
            $reflection = is_array($callable)
                ? new \ReflectionMethod($callable[0], $callable[1])
                : new \ReflectionFunction(\Closure::fromCallable($callable));
        } catch (Throwable $e) {
            return $callable($event, $payload);
        }

        $args = [];
        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();
            if ($name === 'event') {
                $args[] = $event;
            } elseif ($name === 'payload') {
                $args[] = $payload;
            } elseif (!$parameter->isDefaultValueAvailable()) {
                $args[] = $event;
            }
        }

        return $callable(...$args);
    }

    private function matchesWildcard(string $pattern, string $eventName): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (function_exists('fnmatch')) {
            return fnmatch($pattern, $eventName, FNM_NOESCAPE);
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $eventName);
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





