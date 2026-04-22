<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\Cookie\\CookieMiddleware') && !class_exists(__NAMESPACE__ . '\\CookieMiddleware')) {
    class_alias('\\Fnlla\\Cookie\\CookieMiddleware', __NAMESPACE__ . '\\CookieMiddleware');
}


