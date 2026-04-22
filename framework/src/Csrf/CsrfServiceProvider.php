<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Csrf;

use Fnlla\Core\Container;
use Fnlla\Session\SessionInterface;
use Fnlla\Support\ServiceProvider;

final class CsrfServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(CsrfTokenManager::class, function () use ($app): CsrfTokenManager {
            $session = $app->make(SessionInterface::class);
            return new CsrfTokenManager($session);
        });
    }
}
