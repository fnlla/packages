<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Tenancy;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(TenantResolverInterface::class, function () use ($app): TenantResolverInterface {
            $config = $app->config()->get('tenancy', []);
            if (!is_array($config)) {
                $config = [];
            }

            $resolverClass = $config['resolver_class'] ?? null;
            if (is_string($resolverClass) && $resolverClass !== '' && class_exists($resolverClass)) {
                $resolver = new $resolverClass($config);
                if ($resolver instanceof TenantResolverInterface) {
                    return $resolver;
                }
            }

            return new TenantResolver($config);
        });
    }
}
