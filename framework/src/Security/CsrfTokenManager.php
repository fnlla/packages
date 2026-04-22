<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
namespace Fnlla\Security;

if (class_exists('\\Fnlla\\Csrf\\CsrfTokenManager') && !class_exists(__NAMESPACE__ . '\\CsrfTokenManager')) {
    class_alias('\\Fnlla\\Csrf\\CsrfTokenManager', __NAMESPACE__ . '\\CsrfTokenManager');
}


