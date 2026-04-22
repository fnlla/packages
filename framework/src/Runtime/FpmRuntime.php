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
use Fnlla\Support\Psr\Http\Message\ResponseInterface;

final class FpmRuntime implements RuntimeInterface
{
    public function run(KernelInterface $kernel): void
    {
        $kernel->boot();
        $request = Request::fromGlobals();
        $response = $kernel->handle($request);
        $this->emit($response);
    }

    private function emit(ResponseInterface $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header($name . ': ' . $value, false);
                }
            }
        }

        echo (string) $response->getBody();
    }
}





