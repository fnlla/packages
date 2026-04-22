<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (class_exists('\\Fnlla\\Auth\\CallableUserProvider') && !class_exists(__NAMESPACE__ . '\\CallableUserProvider')) {
    class_alias('\\Fnlla\\Auth\\CallableUserProvider', __NAMESPACE__ . '\\CallableUserProvider');
}


