<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\RateLimit\\RateLimiter') && !class_exists(__NAMESPACE__ . '\\RateLimiter')) {
    class_alias('\\Fnlla\\RateLimit\\RateLimiter', __NAMESPACE__ . '\\RateLimiter');
}


