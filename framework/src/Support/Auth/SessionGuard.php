<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (class_exists('\\Fnlla\\Auth\\SessionGuard') && !class_exists(__NAMESPACE__ . '\\SessionGuard')) {
    class_alias('\\Fnlla\\Auth\\SessionGuard', __NAMESPACE__ . '\\SessionGuard');
}


