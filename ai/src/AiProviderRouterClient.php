<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai;

final class AiProviderRouterClient implements AiClientInterface
{
    /**
     * @param array<string, AiClientInterface> $clients
     */
    public function __construct(
        private string $defaultProvider,
        private array $clients,
        private string $fallbackProvider = ''
    ) {
        $this->defaultProvider = $this->defaultProvider !== '' ? $this->defaultProvider : 'openai';
    }

    public function defaultProvider(): string
    {
        return $this->defaultProvider;
    }

    public function responses(array $payload): array
    {
        $provider = $this->resolveProvider($payload);
        $client = $this->clientFor($provider);
        $payload = $this->stripProviderKeys($payload);
        $response = $client->responses($payload);

        if (!$response['ok'] && $this->fallbackProvider !== '' && $this->fallbackProvider !== $provider) {
            $fallbackClient = $this->clientFor($this->fallbackProvider);
            $response = $fallbackClient->responses($payload);
        }

        return $response;
    }

    public function embeddings(array $payload): array
    {
        $provider = $this->resolveProvider($payload);
        $client = $this->clientFor($provider);
        $payload = $this->stripProviderKeys($payload);
        return $client->embeddings($payload);
    }

    public function models(): array
    {
        $client = $this->clientFor($this->defaultProvider);
        return $client->models();
    }

    public function realtimeClientSecret(array $payload = []): array
    {
        $provider = $this->resolveProvider($payload);
        $client = $this->clientFor($provider);
        $payload = $this->stripProviderKeys($payload);
        return $client->realtimeClientSecret($payload);
    }

    private function resolveProvider(array $payload): string
    {
        $provider = '';
        if (isset($payload['__provider']) && is_string($payload['__provider'])) {
            $provider = trim($payload['__provider']);
        } elseif (isset($payload['provider']) && is_string($payload['provider'])) {
            $provider = trim($payload['provider']);
        }

        if ($provider === '') {
            $provider = $this->defaultProvider;
        }

        if (!array_key_exists($provider, $this->clients)) {
            return $this->defaultProvider;
        }

        return $provider;
    }

    private function clientFor(string $provider): AiClientInterface
    {
        if (isset($this->clients[$provider])) {
            return $this->clients[$provider];
        }

        $first = reset($this->clients);
        if ($first instanceof AiClientInterface) {
            return $first;
        }

        throw new \RuntimeException('AI provider client is not configured.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function stripProviderKeys(array $payload): array
    {
        unset($payload['__provider'], $payload['provider']);
        return $payload;
    }
}


