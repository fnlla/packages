<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai;

use Fnlla\Core\ConfigRepository;
use Fnlla\Support\HttpClient;

final class AiManager
{
    private ?AiClientInterface $client = null;
    private ?OpenAiClient $openAi = null;
    /** @var array<string, AiClientInterface> */
    private array $providers = [];

    public function __construct(private HttpClient $http, private ConfigRepository $config)
    {
    }

    public function client(): AiClientInterface
    {
        if ($this->client instanceof AiClientInterface) {
            return $this->client;
        }

        $aiConfig = $this->config->get('ai', []);
        if (!is_array($aiConfig)) {
            $aiConfig = [];
        }
        $aiConfig = $this->applyEnvOverrides($aiConfig);

        $providers = $this->buildProviders($aiConfig);
        $defaultProvider = strtolower((string) ($aiConfig['provider'] ?? 'openai'));
        if ($defaultProvider === '') {
            $defaultProvider = 'openai';
        }

        if (count($providers) <= 1) {
            $this->client = $providers[$defaultProvider] ?? reset($providers);
        } else {
            $fallbackProvider = strtolower((string) ($aiConfig['fallback_provider'] ?? ''));
            $this->client = new AiProviderRouterClient($defaultProvider, $providers, $fallbackProvider);
        }

        return $this->client;
    }

    public function openai(): OpenAiClient
    {
        if ($this->openAi instanceof OpenAiClient) {
            return $this->openAi;
        }

        $aiConfig = $this->config->get('ai', []);
        if (!is_array($aiConfig)) {
            $aiConfig = [];
        }
        $aiConfig = $this->applyEnvOverrides($aiConfig);

        $this->openAi = $this->openAiFromConfig($aiConfig);

        return $this->openAi;
    }

    public function provider(string $name): ?AiClientInterface
    {
        if ($this->providers === []) {
            $aiConfig = $this->config->get('ai', []);
            if (!is_array($aiConfig)) {
                $aiConfig = [];
            }
            $aiConfig = $this->applyEnvOverrides($aiConfig);
            $this->buildProviders($aiConfig);
        }

        $name = strtolower(trim($name));
        if ($name === '') {
            return null;
        }

        return $this->providers[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function openAiFromConfig(array $config): OpenAiClient
    {
        return new OpenAiClient($this->http, $config);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, AiClientInterface>
     */
    private function buildProviders(array $config): array
    {
        if ($this->providers !== []) {
            return $this->providers;
        }

        $providers = [];
        $providerConfig = $config['providers'] ?? [];
        if (!is_array($providerConfig)) {
            $providerConfig = [];
        }

        $defaultProvider = strtolower((string) ($config['provider'] ?? 'openai'));
        if ($defaultProvider === '') {
            $defaultProvider = 'openai';
        }

        $baseConfig = $config;
        unset($baseConfig['providers']);

        $providers[$defaultProvider] = $this->buildProviderClient($defaultProvider, $baseConfig);

        foreach ($providerConfig as $name => $overrides) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (!is_array($overrides)) {
                continue;
            }
            $merged = array_merge($baseConfig, $overrides);
            $providers[$name] = $this->buildProviderClient($name, $merged);
        }

        $this->providers = $providers;

        return $providers;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function applyEnvOverrides(array $config): array
    {
        $driver = getenv('AI_DRIVER');
        if (is_string($driver) && $driver !== '') {
            $config['driver'] = $driver;
        }

        $provider = getenv('AI_PROVIDER');
        if (is_string($provider) && $provider !== '') {
            $config['provider'] = $provider;
        }

        $apiKey = getenv('OPENAI_API_KEY');
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }

        $baseUrl = getenv('OPENAI_BASE_URL');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $config['base_url'] = $baseUrl;
        }

        $model = getenv('OPENAI_MODEL');
        if (is_string($model) && $model !== '') {
            $config['model'] = $model;
        }

        $embeddingModel = getenv('OPENAI_EMBEDDING_MODEL');
        if (is_string($embeddingModel) && $embeddingModel !== '') {
            $config['embedding_model'] = $embeddingModel;
        }

        $realtimeModel = getenv('OPENAI_REALTIME_MODEL');
        if (is_string($realtimeModel) && $realtimeModel !== '') {
            $config['realtime_model'] = $realtimeModel;
        }

        $realtimeVoice = getenv('OPENAI_REALTIME_VOICE');
        if (is_string($realtimeVoice) && $realtimeVoice !== '') {
            $config['realtime_voice'] = $realtimeVoice;
        }

        $realtimeInstructions = getenv('OPENAI_REALTIME_INSTRUCTIONS');
        if (is_string($realtimeInstructions) && $realtimeInstructions !== '') {
            $config['realtime_instructions'] = $realtimeInstructions;
        }

        $organization = getenv('OPENAI_ORG');
        if (is_string($organization) && $organization !== '') {
            $config['organization'] = $organization;
        }

        $project = getenv('OPENAI_PROJECT');
        if (is_string($project) && $project !== '') {
            $config['project'] = $project;
        }

        $fallbackProvider = getenv('AI_FALLBACK_PROVIDER');
        if (is_string($fallbackProvider) && $fallbackProvider !== '') {
            $config['fallback_provider'] = $fallbackProvider;
        }

        $secondaryDriver = getenv('AI_SECONDARY_DRIVER');
        if (is_string($secondaryDriver) && $secondaryDriver !== '') {
            $config['providers']['secondary']['driver'] = $secondaryDriver;
        }
        $secondaryBaseUrl = getenv('AI_SECONDARY_BASE_URL');
        if (is_string($secondaryBaseUrl) && $secondaryBaseUrl !== '') {
            $config['providers']['secondary']['base_url'] = $secondaryBaseUrl;
        }
        $secondaryApiKey = getenv('AI_SECONDARY_API_KEY');
        if (is_string($secondaryApiKey) && $secondaryApiKey !== '') {
            $config['providers']['secondary']['api_key'] = $secondaryApiKey;
        }
        $secondaryModel = getenv('AI_SECONDARY_MODEL');
        if (is_string($secondaryModel) && $secondaryModel !== '') {
            $config['providers']['secondary']['model'] = $secondaryModel;
        }
        $secondaryEmbedding = getenv('AI_SECONDARY_EMBEDDING_MODEL');
        if (is_string($secondaryEmbedding) && $secondaryEmbedding !== '') {
            $config['providers']['secondary']['embedding_model'] = $secondaryEmbedding;
        }
        $secondaryRealtime = getenv('AI_SECONDARY_REALTIME_MODEL');
        if (is_string($secondaryRealtime) && $secondaryRealtime !== '') {
            $config['providers']['secondary']['realtime_model'] = $secondaryRealtime;
        }
        $secondaryVoice = getenv('AI_SECONDARY_REALTIME_VOICE');
        if (is_string($secondaryVoice) && $secondaryVoice !== '') {
            $config['providers']['secondary']['realtime_voice'] = $secondaryVoice;
        }
        $secondaryInstructions = getenv('AI_SECONDARY_REALTIME_INSTRUCTIONS');
        if (is_string($secondaryInstructions) && $secondaryInstructions !== '') {
            $config['providers']['secondary']['realtime_instructions'] = $secondaryInstructions;
        }
        $secondaryOrg = getenv('AI_SECONDARY_ORG');
        if (is_string($secondaryOrg) && $secondaryOrg !== '') {
            $config['providers']['secondary']['organization'] = $secondaryOrg;
        }
        $secondaryProject = getenv('AI_SECONDARY_PROJECT');
        if (is_string($secondaryProject) && $secondaryProject !== '') {
            $config['providers']['secondary']['project'] = $secondaryProject;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildProviderClient(string $name, array $config): AiClientInterface
    {
        $driver = strtolower((string) ($config['driver'] ?? 'openai'));
        $name = strtolower(trim($name));

        if ($driver === '' && $name === 'mock') {
            $driver = 'mock';
        }

        if ($driver === 'mock' || $name === 'mock') {
            return new MockAiClient();
        }

        if ($driver !== 'openai') {
            $driver = 'openai';
        }

        return $this->openAiFromConfig($config);
    }
}



