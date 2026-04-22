<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Cookie\\CookieJar') && !class_exists(__NAMESPACE__ . '\\CookieJar')) {
    class_alias('\\Fnlla\\Cookie\\CookieJar', __NAMESPACE__ . '\\CookieJar');
}


