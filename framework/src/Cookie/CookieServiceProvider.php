<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cookie;

use Fnlla\Support\ServiceProvider;

final class CookieServiceProvider extends ServiceProvider
{
    public function register(\Fnlla\Core\Container $app): void
    {
        $app->singleton(CookieJar::class, fn (): CookieJar => new CookieJar());
    }
}
