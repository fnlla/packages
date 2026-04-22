<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (class_exists('\\Fnlla\\Auth\\AuthManager') && !class_exists(__NAMESPACE__ . '\\AuthManager')) {
    class_alias('\\Fnlla\\Auth\\AuthManager', __NAMESPACE__ . '\\AuthManager');
}


