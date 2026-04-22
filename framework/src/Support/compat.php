<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

if (!function_exists(__NAMESPACE__ . '\\Fnlla_compat_enabled')) {
    function Fnlla_compat_enabled(): bool
    {
        $env = getenv('APP_COMPAT');
        if ($env !== false && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }
        return true;
    }
}

spl_autoload_register(static function (string $class): void {
    if (!Fnlla_compat_enabled()) {
        return;
    }

    $map = [
        'Application' => \Fnlla\Core\Application::class,
        'Router' => \Fnlla\Http\Router::class,
        'Request' => \Fnlla\Http\Request::class,
        'Response' => \Fnlla\Http\Response::class,
    ];

    if (!isset($map[$class])) {
        return;
    }

    if (class_exists($class, false)) {
        return;
    }

    if (class_exists($map[$class])) {
        class_alias($map[$class], $class);
    }
});



