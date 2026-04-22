<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Redirects;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class RedirectsServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        // No bindings required; middleware can be resolved directly.
    }
}
