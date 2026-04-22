<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Sentry;

use Sentry\State\HubInterface;
use Sentry\SentrySdk;

final class SentryManager
{
    private bool $booted = false;

    public function __construct(private array $config = [])
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function init(): void
    {
        if ($this->booted || !$this->enabled()) {
            return;
        }

        $dsn = (string) ($this->config['dsn'] ?? '');
        if ($dsn === '') {
            return;
        }

        $options = [
            'dsn' => $dsn,
            'environment' => (string) ($this->config['environment'] ?? 'local'),
            'traces_sample_rate' => (float) ($this->config['traces_sample_rate'] ?? 0.0),
            'profiles_sample_rate' => (float) ($this->config['profiles_sample_rate'] ?? 0.0),
        ];

        $release = (string) ($this->config['release'] ?? '');
        if ($release !== '') {
            $options['release'] = $release;
        }

        \Sentry\init($options);

        $this->booted = true;
    }

    public function hub(): ?HubInterface
    {
        if (!$this->booted) {
            $this->init();
        }

        return SentrySdk::getCurrentHub();
    }

    public function captureException(\Throwable $exception): ?string
    {
        if (!$this->enabled()) {
            return null;
        }

        if (!$this->booted) {
            $this->init();
        }

        $eventId = \Sentry\captureException($exception);
        return $eventId ? (string) $eventId : null;
    }

    public function captureMessage(string $message, ?\Sentry\Severity $level = null): ?string
    {
        if (!$this->enabled()) {
            return null;
        }

        if (!$this->booted) {
            $this->init();
        }

        $eventId = \Sentry\captureMessage($message, $level);
        return $eventId ? (string) $eventId : null;
    }
}
