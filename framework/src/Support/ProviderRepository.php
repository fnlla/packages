<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Core\ConfigRepository;
use Fnlla\Contracts\Support\ServiceProviderInterface;
use Fnlla\Core\Container;
use RuntimeException;

final class ProviderRepository
{
    private array $providers = [];
    private array $instances = [];
    private bool $validated = false;
    private ProviderValidator $validator;
    private ?ProviderReport $report = null;

    public function __construct(private Container $app)
    {
        $this->validator = new ProviderValidator();
        $this->report = $this->resolveReport();
    }

    public function add(string $fqcn, int $priority = 0, string $source = 'auto', bool $enabled = true, array $meta = []): void
    {
        if ($fqcn === '') {
            return;
        }

        if (!is_array($meta)) {
            $meta = [];
        }

        $manifest = $this->resolveManifest($fqcn);
        if (!isset($meta['source'])) {
            $meta['source'] = $source;
        }
        if ($meta !== []) {
            $manifest->meta = array_merge($manifest->meta, $meta);
        }
        $this->providers[$fqcn] = [
            'priority' => $priority,
            'source' => $source,
            'enabled' => $enabled,
            'manifest' => $manifest,
            'issues' => [],
        ];

        $this->validated = false;
        if ($this->report instanceof ProviderReport) {
            $this->report->addEntry(
                $fqcn,
                $source,
                $enabled,
                $priority,
                $manifest->capabilities,
                []
            );
        }
    }

    public function registerAll(): void
    {
        $this->validateAll();
        foreach ($this->sortedProviders() as $providerClass) {
            $provider = $this->resolveProvider($providerClass);
            $provider->register($this->app);
        }
    }

    public function bootAll(): void
    {
        $this->validateAll();
        foreach ($this->sortedProviders() as $providerClass) {
            $provider = $this->resolveProvider($providerClass);
            $provider->boot($this->app);
        }
    }

    private function sortedProviders(): array
    {
        $items = [];
        foreach ($this->providers as $fqcn => $meta) {
            $items[] = [
                'class' => $fqcn,
                'priority' => (int) ($meta['priority'] ?? 0),
            ];
        }

        usort($items, function (array $left, array $right): int {
            $byPriority = $left['priority'] <=> $right['priority'];
            if ($byPriority !== 0) {
                return $byPriority;
            }
            return strcmp($left['class'], $right['class']);
        });

        return array_map(static fn (array $item): string => $item['class'], $items);
    }

    private function validateAll(): void
    {
        if ($this->validated) {
            return;
        }

        $appRoot = $this->resolveAppRoot();
        foreach ($this->sortedProviders() as $fqcn) {
            $meta = $this->providers[$fqcn] ?? null;
            if (!is_array($meta)) {
                continue;
            }

            $manifest = $meta['manifest'] ?? null;
            if (!$manifest instanceof ProviderManifest) {
                $manifest = new ProviderManifest($fqcn, [], [], []);
            }

            $issues = [];
            try {
                $issues = $this->validator->validate($manifest, $appRoot);
            } catch (RuntimeException $e) {
                $issues = $this->parseIssues($e->getMessage());
                $this->recordIssues($fqcn, $issues);
                throw new RuntimeException(
                    'Provider validation failed for ' . $fqcn . ': ' . implode('; ', $issues),
                    0,
                    $e
                );
            }

            $this->recordIssues($fqcn, $issues);
        }

        $this->validated = true;
    }

    private function recordIssues(string $fqcn, array $issues): void
    {
        if (!isset($this->providers[$fqcn])) {
            return;
        }

        $this->providers[$fqcn]['issues'] = $issues;

        if ($this->report instanceof ProviderReport) {
            $meta = $this->providers[$fqcn];
            $manifest = $meta['manifest'] instanceof ProviderManifest
                ? $meta['manifest']
                : new ProviderManifest($fqcn, [], [], []);
            $this->report->addEntry(
                $fqcn,
                (string) ($meta['source'] ?? 'auto'),
                (bool) ($meta['enabled'] ?? true),
                (int) ($meta['priority'] ?? 0),
                $manifest->capabilities,
                $issues
            );
        }
    }

    private function parseIssues(string $message): array
    {
        $prefix = 'Provider manifest validation failed: ';
        if (str_starts_with($message, $prefix)) {
            $rest = trim(substr($message, strlen($prefix)));
            if ($rest === '') {
                return [$message];
            }
            return array_map('trim', explode(';', $rest));
        }

        return [$message];
    }

    private function resolveManifest(string $fqcn): ProviderManifest
    {
        if (class_exists($fqcn) && method_exists($fqcn, 'manifest')) {
            $manifest = $fqcn::manifest();
            if ($manifest instanceof ProviderManifest) {
                return $manifest;
            }
        }

        return new ProviderManifest($fqcn, [], [], []);
    }

    private function resolveAppRoot(): string
    {
        if (method_exists($this->app, 'basePath')) {
            $base = (string) $this->app->basePath();
            if ($base !== '') {
                return $base;
            }
        }

        return ConfigRepository::resolveAppRoot();
    }

    private function resolveReport(): ?ProviderReport
    {
        if (!$this->app->has(ProviderReport::class)) {
            return null;
        }

        $report = $this->app->make(ProviderReport::class);
        return $report instanceof ProviderReport ? $report : null;
    }

    private function resolveProvider(string $fqcn): ServiceProviderInterface
    {
        if (isset($this->instances[$fqcn])) {
            return $this->instances[$fqcn];
        }

        if (!class_exists($fqcn)) {
            throw new RuntimeException('Provider class not found: ' . $fqcn);
        }

        $provider = $this->app->make($fqcn, ['app' => $this->app]);
        if (!$provider instanceof ServiceProviderInterface) {
            throw new RuntimeException('Provider [' . $fqcn . '] must implement ServiceProviderInterface.');
        }

        $this->instances[$fqcn] = $provider;
        return $provider;
    }
}
