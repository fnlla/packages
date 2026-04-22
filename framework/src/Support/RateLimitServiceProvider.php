<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\RateLimit\\RateLimitServiceProvider') && !class_exists(__NAMESPACE__ . '\\RateLimitServiceProvider')) {
    class_alias('\\Fnlla\\RateLimit\\RateLimitServiceProvider', __NAMESPACE__ . '\\RateLimitServiceProvider');
}


