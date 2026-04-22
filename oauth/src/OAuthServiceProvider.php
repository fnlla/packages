<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\OAuth;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class OAuthServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(OAuthManager::class, function () use ($app): OAuthManager {
            $config = $app->config()->get('oauth', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new OAuthManager($config);
        });
    }
}
