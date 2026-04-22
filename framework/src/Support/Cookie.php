<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Cookie\\Cookie') && !class_exists(__NAMESPACE__ . '\\Cookie')) {
    class_alias('\\Fnlla\\Cookie\\Cookie', __NAMESPACE__ . '\\Cookie');
}


