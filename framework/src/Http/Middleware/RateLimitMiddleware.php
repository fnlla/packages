<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\RateLimit\\RateLimitMiddleware') && !class_exists(__NAMESPACE__ . '\\RateLimitMiddleware')) {
    class_alias('\\Fnlla\\RateLimit\\RateLimitMiddleware', __NAMESPACE__ . '\\RateLimitMiddleware');
}


