<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (class_exists('\\Fnlla\\Log\\LogServiceProvider') && !class_exists(__NAMESPACE__ . '\\LogServiceProvider')) {
    class_alias('\\Fnlla\\Log\\LogServiceProvider', __NAMESPACE__ . '\\LogServiceProvider');
}


