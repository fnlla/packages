<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Runtime;

final class ResetManager
{
    /** @var Resetter[] */
    private array $resetters = [];

    public function register(Resetter $resetter): void
    {
        $this->resetters[] = $resetter;
    }

    public function reset(): void
    {
        foreach ($this->resetters as $resetter) {
            $resetter->reset();
        }
    }
}



