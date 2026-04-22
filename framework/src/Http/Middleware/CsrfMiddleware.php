<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\Csrf\\CsrfMiddleware') && !class_exists(__NAMESPACE__ . '\\CsrfMiddleware')) {
    class_alias('\\Fnlla\\Csrf\\CsrfMiddleware', __NAMESPACE__ . '\\CsrfMiddleware');
}


