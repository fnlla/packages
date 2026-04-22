<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Cookie\\CookieServiceProvider') && !class_exists(__NAMESPACE__ . '\\CookieServiceProvider')) {
    class_alias('\\Fnlla\\Cookie\\CookieServiceProvider', __NAMESPACE__ . '\\CookieServiceProvider');
}


