<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Plugin;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Application;

final class PluginManager
{
    private array $instances = [];

    public function __construct(private ConfigRepository $config, private ?Application $app = null)
    {
    }

    public function config(): ConfigRepository
    {
        return $this->config;
    }

    public function app(): ?Application
    {
        return $this->app;
    }

    public function load(array $pluginClasses): void
    {
        foreach ($pluginClasses as $pluginClass) {
            if (!is_string($pluginClass) || $pluginClass === '') {
                continue;
            }
            if (!class_exists($pluginClass)) {
                continue;
            }
            $plugin = $this->app ? $this->app->make($pluginClass) : new $pluginClass();
            $this->instances[] = $plugin;
            if ($plugin instanceof PluginInterface) {
                $plugin->register($this);
            }
        }
    }

    public function boot(): void
    {
        foreach ($this->instances as $plugin) {
            if (method_exists($plugin, 'boot')) {
                $plugin->boot($this);
            }
        }
    }

    public function all(): array
    {
        return $this->instances;
    }
}





