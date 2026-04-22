<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Factory;

use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;

interface ServerRequestFactoryInterface
{
    /**
     * @param mixed $uri
     */
    public function createServerRequest(string $method, mixed $uri, array $serverParams = []): ServerRequestInterface;
}






