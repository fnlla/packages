<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Log;

use Fnlla\Contracts\Log\LoggerInterface as FnllaLoggerInterface;
use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class LogServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(LoggerFactory::class, function () use ($app): LoggerFactory {
            $config = $app->config()->get('logging', null);
            if (!is_array($config) || $config === []) {
                $fallback = $app->config()->get('log', []);
                $config = is_array($fallback) ? $fallback : [];
            }

            return new LoggerFactory($config, $app);
        });

        $app->singleton(LoggerInterface::class, function () use ($app): LoggerInterface {
            return $app->make(LoggerFactory::class)->make('app');
        });

        $app->singleton(FnllaLoggerInterface::class, function () use ($app): LoggerInterface {
            return $app->make(LoggerInterface::class);
        });
    }
}
