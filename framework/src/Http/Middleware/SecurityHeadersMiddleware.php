<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\SecurityHeaders\\SecurityHeadersMiddleware') && !class_exists(__NAMESPACE__ . '\\SecurityHeadersMiddleware')) {
    class_alias('\\Fnlla\\SecurityHeaders\\SecurityHeadersMiddleware', __NAMESPACE__ . '\\SecurityHeadersMiddleware');
}


