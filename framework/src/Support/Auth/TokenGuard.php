<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (class_exists('\\Fnlla\\Auth\\TokenGuard') && !class_exists(__NAMESPACE__ . '\\TokenGuard')) {
    class_alias('\\Fnlla\\Auth\\TokenGuard', __NAMESPACE__ . '\\TokenGuard');
}


