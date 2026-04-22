<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Support;

use Fnlla\Core\Container;

/**
 * @api
 */
interface ServiceProviderInterface
{
    public function register(Container $app): void;

    public function boot(Container $app): void;

    public static function manifest(): \Fnlla\Support\ProviderManifest;
}

