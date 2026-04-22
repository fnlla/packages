<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\RequestLogging\\RequestLoggerMiddleware') && !class_exists(__NAMESPACE__ . '\\RequestLoggerMiddleware')) {
    class_alias('\\Fnlla\\RequestLogging\\RequestLoggerMiddleware', __NAMESPACE__ . '\\RequestLoggerMiddleware');
}


