<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Policy;

use Fnlla\Ai\AiClientInterface;
use Fnlla\Ai\Redaction\AiRedactor;
use Fnlla\Ai\Router\AiRouter;
use Fnlla\Ai\Telemetry\AiTelemetryService;

final class AiPolicyClient implements AiClientInterface
{
    public function __construct(
        private AiClientInterface $inner,
        private AiPolicy $policy,
        private ?AiTelemetryService $telemetry = null,
        private ?AiRedactor $redactor = null,
        private ?AiRouter $router = null
    ) {
    }

    public function inner(): AiClientInterface
    {
        return $this->inner;
    }

    public function responses(array $payload): array
    {
        $telemetryMeta = [];
        if (isset($payload['__telemetry']) && is_array($payload['__telemetry'])) {
            $telemetryMeta = $payload['__telemetry'];
        }
        unset($payload['__telemetry']);

        $route = '';
        if (isset($payload['__route']) && is_string($payload['__route'])) {
            $route = $payload['__route'];
        }
        unset($payload['__route']);

        $policyPack = '';
        if (isset($payload['__policy_pack']) && is_string($payload['__policy_pack'])) {
            $policyPack = trim($payload['__policy_pack']);
        }
        unset($payload['__policy_pack']);
        if ($policyPack !== '') {
            $telemetryMeta['policy_pack'] = $policyPack;
        }

        $policy = $policyPack !== '' ? $this->policy->withPack($policyPack) : $this->policy;
        $payload = $policy->applyResponsesPayload($payload);
        $telemetryMeta['policy'] = [
            'temperature' => $payload['temperature'] ?? null,
            'max_output_tokens' => $payload['max_output_tokens'] ?? null,
            'max_input_chars' => $policy->maxInputChars(),
            'require_rag' => $policy->requireRag(),
            'rag_min_sources' => $policy->ragMinSources(),
            'rag_min_score' => $policy->ragMinScore(),
        ];

        $providerOverride = '';
        if (isset($payload['__provider']) && is_string($payload['__provider'])) {
            $providerOverride = trim($payload['__provider']);
        }
        if ($this->redactor instanceof AiRedactor && $this->redactor->enabled()) {
            $payload = $this->redactor->redactPayload($payload);
            if ($telemetryMeta !== []) {
                $telemetryMeta = $this->redactor->redactArray($telemetryMeta);
            }
            $telemetryMeta['redaction'] = ['enabled' => true];
        } else {
            $telemetryMeta['redaction'] = ['enabled' => false];
        }

        if ($this->router instanceof AiRouter && $this->router->enabled()) {
            $model = (string) ($payload['model'] ?? '');
            $resolved = $this->router->resolveRoute($route);
            if ($model === '') {
                $resolvedModel = (string) ($resolved['model'] ?? '');
                if ($resolvedModel !== '') {
                    $payload['model'] = $resolvedModel;
                }
            }
            $routeProvider = (string) ($resolved['provider'] ?? '');
            if ($routeProvider !== '' && $providerOverride === '') {
                $payload['__provider'] = $routeProvider;
                $providerOverride = $routeProvider;
                $telemetryMeta['provider_route'] = $routeProvider;
            }
            if ($route !== '') {
                $telemetryMeta['route'] = $route;
            }
        }

        $response = $this->inner->responses($payload);

        if (!$response['ok'] && $this->router instanceof AiRouter && $this->router->enabled()) {
            $fallback = $this->router->fallbackModel();
            $currentModel = (string) ($payload['model'] ?? '');
            if ($fallback !== '' && $fallback !== $currentModel) {
                $payload['model'] = $fallback;
                $telemetryMeta['fallback_used'] = true;
                $telemetryMeta['fallback_model'] = $fallback;
                $response = $this->inner->responses($payload);
            }
            if (!$response['ok']) {
                $fallbackProvider = $this->router->fallbackProvider();
                if ($fallbackProvider !== '' && $fallbackProvider !== $providerOverride) {
                    $payload['__provider'] = $fallbackProvider;
                    $providerOverride = $fallbackProvider;
                    $telemetryMeta['fallback_used'] = true;
                    $telemetryMeta['fallback_provider'] = $fallbackProvider;
                    $response = $this->inner->responses($payload);
                }
            }
        }

        if ($this->telemetry instanceof AiTelemetryService && $this->telemetry->enabled()) {
            if ($providerOverride !== '') {
                $telemetryMeta['provider'] = $providerOverride;
            }
            $telemetryMeta['provider'] = $telemetryMeta['provider'] ?? $this->guessProvider();
            $runId = $this->telemetry->record($payload, $response, $telemetryMeta);
            if ($runId !== null) {
                $response['telemetry'] = ['run_id' => $runId];
            }
        }

        return $response;
    }

    public function embeddings(array $payload): array
    {
        return $this->inner->embeddings($payload);
    }

    public function models(): array
    {
        return $this->inner->models();
    }

    public function realtimeClientSecret(array $payload = []): array
    {
        return $this->inner->realtimeClientSecret($payload);
    }

    private function guessProvider(): string
    {
        $class = get_class($this->inner);
        $parts = explode('\\', $class);
        $short = strtolower((string) end($parts));
        return $short !== '' ? $short : 'ai';
    }
}

