<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Core;

use Fnlla\Core\ConfigRepository;
use Fnlla\Support\ProviderReport;
use Fnlla\Authorization\Gate;
use Fnlla\Authorization\PolicyRegistry;
use RuntimeException;
use Fnlla\Core\ServiceProvider;

/**
 * @api
 */
final class Application extends Container
{
    public const VERSION = '3.0.0';
    public const NAME_ORIGIN = 'Fnlla Gardens, Dundee, UK.';
    public const TECHNICAL_SLUG = 'fnlla';

    private array $providers = [];
    private ConfigRepository $config;

    public function __construct(private string $basePath, ConfigRepository $config)
    {
        $this->config = $config;

        $this->instance(ConfigRepository::class, $this->config);
        $this->instance('config', $this->config);
        $this->instance('config.repo', $this->config);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);
        $this->singleton(ProviderReport::class, fn (): ProviderReport => new ProviderReport());
        $this->singleton(PolicyRegistry::class, function (): PolicyRegistry {
            $policies = $this->config->get('authorization.policies', []);
            $guess = $this->config->get('authorization.guess', true);
            if (!is_array($policies)) {
                $policies = [];
            }
            return new PolicyRegistry($policies, (bool) $guess);
        });
        $this->singleton(Gate::class, fn (): Gate => new Gate($this, $this->make(PolicyRegistry::class)));
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    public function config(): ConfigRepository
    {
        return $this->config;
    }

    public function configRepository(): ConfigRepository
    {
        return $this->config;
    }

    public function registerProvider(string $providerClass): void
    {
        if (!class_exists($providerClass)) {
            return;
        }

        $provider = new $providerClass($this);
        if (!$provider instanceof ServiceProvider) {
            throw new RuntimeException('Provider [' . $providerClass . '] must extend ServiceProvider.');
        }

        $provider->register();
        $this->providers[] = $provider;
    }

    public function registerProviders(array $providers): void
    {
        foreach ($providers as $providerClass) {
            if (!is_string($providerClass) || $providerClass === '') {
                continue;
            }
            $this->registerProvider($providerClass);
        }
    }

    public function bootProviders(): void
    {
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }
}


