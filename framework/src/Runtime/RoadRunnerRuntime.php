<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Runtime;

use Fnlla\Contracts\Http\KernelInterface;
use Fnlla\Contracts\Runtime\RuntimeInterface;
use Fnlla\Http\Request;
use RuntimeException;
use Throwable;

final class RoadRunnerRuntime implements RuntimeInterface
{
    public function __construct(private ?object $worker = null)
    {
    }

    public function run(KernelInterface $kernel): void
    {
        if (!class_exists(\Spiral\RoadRunner\Worker::class) || !class_exists(\Spiral\RoadRunner\Http\PSR7Worker::class)) {
            throw new RuntimeException('RoadRunner packages are not installed.');
        }

        $kernel->boot();

        $worker = $this->worker;
        if ($worker === null) {
            $worker = new \Spiral\RoadRunner\Http\PSR7Worker(\Spiral\RoadRunner\Worker::create());
        }

        while ($psrRequest = $worker->waitRequest()) {
            try {
                $request = $psrRequest instanceof Request
                    ? $psrRequest
                    : Request::fromPsr($psrRequest);
                $response = $kernel->handle($request);
                $worker->respond($response);
            } catch (Throwable $e) {
                try {
                    $worker->getWorker()->error((string) $e);
                } catch (Throwable $ignored) {
                }
            }
        }
    }
}




