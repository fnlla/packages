<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Support\Auth;

if (interface_exists('\\Fnlla\\Auth\\UserProviderInterface') && !interface_exists(__NAMESPACE__ . '\\UserProviderInterface')) {
    class_alias('\\Fnlla\\Auth\\UserProviderInterface', __NAMESPACE__ . '\\UserProviderInterface');
}


