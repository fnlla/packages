<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Session\\SessionServiceProvider') && !class_exists(__NAMESPACE__ . '\\SessionServiceProvider')) {
    class_alias('\\Fnlla\\Session\\SessionServiceProvider', __NAMESPACE__ . '\\SessionServiceProvider');
}


