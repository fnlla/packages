<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Http;

use Fnlla\Http\Request;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;

/**
 * @api
 */
interface KernelInterface
{
    public function boot(?string $appRoot = null): void;

    public function handle(Request $request): ResponseInterface;
}






