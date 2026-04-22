<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Plugin;

interface PluginInterface
{
    public function register(PluginManager $plugins): void;
}



