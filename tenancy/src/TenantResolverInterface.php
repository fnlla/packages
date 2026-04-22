<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Tenancy;

use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;

interface TenantResolverInterface
{
    public function resolve(ServerRequestInterface $request): ?string;
}
