<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (class_exists('\\Fnlla\\Auth\\AuthServiceProvider') && !class_exists(__NAMESPACE__ . '\\AuthServiceProvider')) {
    class_alias('\\Fnlla\\Auth\\AuthServiceProvider', __NAMESPACE__ . '\\AuthServiceProvider');
}


