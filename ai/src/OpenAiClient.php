<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai;

use Fnlla\Support\HttpClient;

final class OpenAiClient implements AiClientInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private string $embeddingModel;
    private string $realtimeModel;
    private string $realtimeVoice;
    private string $realtimeInstructions;
    private string $organization;
    private string $project;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private HttpClient $http, array $config = [])
    {
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->model = (string) ($config['model'] ?? '');
        $this->embeddingModel = (string) ($config['embedding_model'] ?? '');
        $this->realtimeModel = (string) ($config['realtime_model'] ?? '');
        $this->realtimeVoice = (string) ($config['realtime_voice'] ?? '');
        $this->realtimeInstructions = (string) ($config['realtime_instructions'] ?? '');
        $this->organization = (string) ($config['organization'] ?? '');
        $this->project = (string) ($config['project'] ?? '');

        $headers = $config['headers'] ?? [];
        $this->headers = is_array($headers) ? $this->normaliseHeaders($headers) : [];
    }

    public function responses(array $payload): array
    {
        $guard = $this->guardConfigured();
        if ($guard !== null) {
            return $guard;
        }

        if (!isset($payload['model']) || $payload['model'] === '') {
            if ($this->model === '') {
                return $this->missingModel('OPENAI_MODEL');
            }
            $payload['model'] = $this->model;
        }

        return $this->postJson('/responses', $payload);
    }

    public function embeddings(array $payload): array
    {
        $guard = $this->guardConfigured();
        if ($guard !== null) {
            return $guard;
        }

        if (!isset($payload['model']) || $payload['model'] === '') {
            if ($this->embeddingModel === '') {
                return $this->missingModel('OPENAI_EMBEDDING_MODEL');
            }
            $payload['model'] = $this->embeddingModel;
        }

        return $this->postJson('/embeddings', $payload);
    }

    public function models(): array
    {
        $guard = $this->guardConfigured();
        if ($guard !== null) {
            return $guard;
        }

        return $this->http->getJson($this->url('/models'), $this->authHeaders());
    }

    public function realtimeClientSecret(array $payload = []): array
    {
        $guard = $this->guardConfigured();
        if ($guard !== null) {
            return $guard;
        }

        if (!isset($payload['session']) || !is_array($payload['session'])) {
            $payload['session'] = [];
        }

        $session = $payload['session'];
        if (!isset($session['type']) || $session['type'] === '') {
            $session['type'] = 'realtime';
        }

        if (!isset($session['model']) || $session['model'] === '') {
            if ($this->realtimeModel === '') {
                return $this->missingModel('OPENAI_REALTIME_MODEL');
            }
            $session['model'] = $this->realtimeModel;
        }

        if ($this->realtimeVoice !== '') {
            $audio = $session['audio'] ?? [];
            if (!is_array($audio)) {
                $audio = [];
            }
            $output = $audio['output'] ?? [];
            if (!is_array($output)) {
                $output = [];
            }
            if (!isset($output['voice']) || $output['voice'] === '') {
                $output['voice'] = $this->realtimeVoice;
            }
            $audio['output'] = $output;
            $session['audio'] = $audio;
        }

        if ($this->realtimeInstructions !== '' && (!isset($session['instructions']) || $session['instructions'] === '')) {
            $session['instructions'] = $this->realtimeInstructions;
        }

        $payload['session'] = $session;

        return $this->postJson('/realtime/client_secrets', $payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    private function postJson(string $path, array $payload): array
    {
        return $this->http->postJsonJson($this->url($path), $payload, $this->authHeaders());
    }

    /**
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}|null
     */
    private function guardConfigured(): ?array
    {
        if ($this->apiKey === '') {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'error' => 'OpenAI API key is not configured.',
            ];
        }

        return null;
    }

    private function missingModel(string $envKey): array
    {
        return [
            'ok' => false,
            'status' => 0,
            'data' => null,
            'error' => 'Model is not configured. Set ' . $envKey . ' or pass a model in the payload.',
        ];
    }

    private function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normaliseHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
        ];

        if ($this->organization !== '') {
            $headers['OpenAI-Organization'] = $this->organization;
        }

        if ($this->project !== '') {
            $headers['OpenAI-Project'] = $this->project;
        }

        if ($this->headers !== []) {
            $headers = array_merge($headers, $this->headers);
        }

        return $headers;
    }
}


