<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Stripe;

use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Event;

final class StripeManager
{
    public function __construct(private array $config = [])
    {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function client(): StripeClient
    {
        $secret = (string) ($this->config['secret'] ?? '');
        if ($secret === '') {
            throw new RuntimeException('Stripe secret is not configured.');
        }

        $options = ['api_key' => $secret];
        $version = (string) ($this->config['api_version'] ?? '');
        if ($version !== '') {
            $options['stripe_version'] = $version;
        }
        $timeout = $this->config['timeout'] ?? null;
        if ($timeout !== null && is_numeric($timeout)) {
            $options['timeout'] = (int) $timeout;
        }

        return new StripeClient($options);
    }

    public function webhookSecret(): string
    {
        return (string) ($this->config['webhook_secret'] ?? '');
    }

    public function constructWebhookEvent(string $payload, string $signatureHeader, ?int $tolerance = null): Event
    {
        $secret = $this->webhookSecret();
        if ($secret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        if ($tolerance === null) {
            return Webhook::constructEvent($payload, $signatureHeader, $secret);
        }

        return Webhook::constructEvent($payload, $signatureHeader, $secret, $tolerance);
    }
}
