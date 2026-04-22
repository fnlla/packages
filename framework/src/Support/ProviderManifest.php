<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use InvalidArgumentException;

final class ProviderManifest
{
    public string $provider;
    public array $capabilities;
    public array $meta;
    public array $resources;

    public function __construct(string $provider, array $capabilities = [], array $meta = [], array $resources = [])
    {
        $provider = trim($provider);
        if ($provider === '') {
            throw new InvalidArgumentException('Provider FQCN must be a non-empty string.');
        }

        foreach ($capabilities as $capability) {
            if (!is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException('Capabilities must be non-empty strings.');
            }
        }

        if (!is_array($meta)) {
            throw new InvalidArgumentException('Meta must be an array.');
        }

        if (!is_array($resources)) {
            throw new InvalidArgumentException('Resources must be an array.');
        }

        $this->provider = $provider;
        $this->capabilities = array_values($capabilities);
        $this->meta = $meta;
        $this->resources = $resources;
    }
}
