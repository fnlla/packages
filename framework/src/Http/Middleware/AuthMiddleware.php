<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Http\Middleware;

if (class_exists('\\Fnlla\\Auth\\Middleware\\AuthMiddleware') && !class_exists(__NAMESPACE__ . '\\AuthMiddleware')) {
    class_alias('\\Fnlla\\Auth\\Middleware\\AuthMiddleware', __NAMESPACE__ . '\\AuthMiddleware');
}


