<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\Session\\SessionMiddleware') && !class_exists(__NAMESPACE__ . '\\SessionMiddleware')) {
    class_alias('\\Fnlla\\Session\\SessionMiddleware', __NAMESPACE__ . '\\SessionMiddleware');
}


