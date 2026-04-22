<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Log\\Logger') && !class_exists(__NAMESPACE__ . '\\Logger')) {
    class_alias('\\Fnlla\\Log\\Logger', __NAMESPACE__ . '\\Logger');
}


