<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Router;

final class AiRouter
{
    private bool $enabled;
    private string $defaultRoute;
    private string $fastModel;
    private string $qualityModel;
    private string $costModel;
    private string $fallbackModel;
    private string $fastProvider;
    private string $qualityProvider;
    private string $costProvider;
    private string $fallbackProvider;
    private float $abRatio;
    private string $abProvider;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->defaultRoute = (string) ($config['default_route'] ?? 'quality');
        $this->fastModel = (string) ($config['fast_model'] ?? '');
        $this->qualityModel = (string) ($config['quality_model'] ?? '');
        $this->costModel = (string) ($config['cost_model'] ?? '');
        $this->fallbackModel = (string) ($config['fallback_model'] ?? '');
        $this->fastProvider = (string) ($config['fast_provider'] ?? '');
        $this->qualityProvider = (string) ($config['quality_provider'] ?? '');
        $this->costProvider = (string) ($config['cost_provider'] ?? '');
        $this->fallbackProvider = (string) ($config['fallback_provider'] ?? '');
        $this->abRatio = (float) ($config['ab_ratio'] ?? 0.0);
        $this->abProvider = (string) ($config['ab_provider'] ?? '');
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function resolve(string $route): string
    {
        $resolved = $this->resolveRoute($route);
        return (string) ($resolved['model'] ?? '');
    }

    /**
     * @return array{model: string, provider: string}
     */
    public function resolveRoute(string $route): array
    {
        $route = trim($route);
        if ($route === '') {
            $route = $this->defaultRoute;
        }

        $model = match ($route) {
            'fast' => $this->fastModel,
            'quality' => $this->qualityModel,
            'cost' => $this->costModel,
            default => $this->qualityModel !== '' ? $this->qualityModel : $this->fastModel,
        };

        $provider = match ($route) {
            'fast' => $this->fastProvider,
            'quality' => $this->qualityProvider,
            'cost' => $this->costProvider,
            default => $this->qualityProvider !== '' ? $this->qualityProvider : $this->fastProvider,
        };

        if ($route === 'quality' && $this->abProvider !== '' && $this->abRatio > 0.0) {
            $roll = mt_rand(1, 1000) / 1000;
            if ($roll <= $this->abRatio) {
                $provider = $this->abProvider;
            }
        }

        return [
            'model' => $model,
            'provider' => $provider,
        ];
    }

    public function fallbackModel(): string
    {
        return $this->fallbackModel;
    }

    public function fallbackProvider(): string
    {
        return $this->fallbackProvider;
    }
}


