<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Monitoring;

use Fnlla\Http\Router;

final class MonitoringRoutes
{
    public static function register(Router $router, array $options = []): void
    {
        $path = (string) ($options['path'] ?? '/metrics');
        $middleware = $options['middleware'] ?? [MonitoringAccessMiddleware::class];
        if (!is_array($middleware)) {
            $middleware = [$middleware];
        }

        $router->get($path, [MonitoringController::class, 'show'], 'monitoring.metrics', $middleware);
    }
}
