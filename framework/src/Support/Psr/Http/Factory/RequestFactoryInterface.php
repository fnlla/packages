<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Factory;

use Fnlla\Support\Psr\Http\Message\RequestInterface;

interface RequestFactoryInterface
{
    /**
     * @param mixed $uri
     */
    public function createRequest(string $method, mixed $uri): RequestInterface;
}






