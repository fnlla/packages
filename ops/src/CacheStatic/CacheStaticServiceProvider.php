<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\CacheStatic;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class CacheStaticServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        // No bindings required; middleware can be resolved directly.
    }
}
