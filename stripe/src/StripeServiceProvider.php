<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Stripe;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class StripeServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(StripeManager::class, function () use ($app): StripeManager {
            $config = $app->config()->get('stripe', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new StripeManager($config);
        });
    }
}
