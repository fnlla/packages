<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support;

if (interface_exists('\\Fnlla\\Session\\SessionInterface') && !interface_exists(__NAMESPACE__ . '\\SessionInterface')) {
    class_alias('\\Fnlla\\Session\\SessionInterface', __NAMESPACE__ . '\\SessionInterface');
}


