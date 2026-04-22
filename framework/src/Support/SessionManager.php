<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Session\\SessionManager') && !class_exists(__NAMESPACE__ . '\\SessionManager')) {
    class_alias('\\Fnlla\\Session\\SessionManager', __NAMESPACE__ . '\\SessionManager');
}


