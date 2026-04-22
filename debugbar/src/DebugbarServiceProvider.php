<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Debugbar;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Debugbar\Middleware\DebugbarMiddleware;
use Fnlla\Support\Env;
use Fnlla\Support\ServiceProvider;

final class DebugbarServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(DebugbarCollector::class, fn (): DebugbarCollector => new DebugbarCollector());
        $app->singleton(DebugbarMiddleware::class, fn (): DebugbarMiddleware => new DebugbarMiddleware());
    }

    public function boot(Container $app): void
    {
        $debug = Env::get('APP_DEBUG', false);
        $env = strtolower((string) Env::get('APP_ENV', ''));
        if ($env === '') {
            $env = 'prod';
        }
        $debugEnabled = $debug === true || $debug === 1 || $debug === '1';
        if ($env === 'prod' && !$debugEnabled) {
            return;
        }

        if (!method_exists($app, 'config')) {
            return;
        }

        $config = $app->config();
        if (!$config instanceof ConfigRepository) {
            return;
        }

        $global = $config->get('http.global', []);
        if (!is_array($global)) {
            $global = [];
        }

        $global[] = DebugbarMiddleware::class;
        $config->set('http.global', array_values(array_unique($global)));
    }
}
