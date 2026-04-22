<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Policy;

final class AiPolicy
{
    /** @var array<string, mixed> */
    private array $config;
    /** @var array<string, array<string, mixed>> */
    private array $packs;
    private bool $enabled;
    private float $defaultTemperature;
    private float $maxTemperature;
    private int $defaultOutputTokens;
    private int $maxOutputTokens;
    private int $maxInputChars;
    private bool $requireRag;
    private int $ragMinSources;
    private float $ragMinScore;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->defaultTemperature = (float) ($config['default_temperature'] ?? 0.2);
        $this->maxTemperature = (float) ($config['max_temperature'] ?? 1.0);
        $this->defaultOutputTokens = (int) ($config['default_output_tokens'] ?? 800);
        $this->maxOutputTokens = (int) ($config['max_output_tokens'] ?? 1200);
        $this->maxInputChars = (int) ($config['max_input_chars'] ?? 12000);
        $this->requireRag = (bool) ($config['require_rag'] ?? false);
        $this->ragMinSources = (int) ($config['rag_min_sources'] ?? 1);
        $this->ragMinScore = (float) ($config['rag_min_score'] ?? 0.2);
        $packs = $config['packs'] ?? [];
        $this->packs = is_array($packs) ? $packs : [];
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function requireRag(): bool
    {
        return $this->requireRag;
    }

    public function ragMinSources(): int
    {
        return max(0, $this->ragMinSources);
    }

    public function ragMinScore(): float
    {
        return max(0.0, $this->ragMinScore);
    }

    public function maxInputChars(): int
    {
        return max(0, $this->maxInputChars);
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    /**
     * @return array<int, string>
     */
    public function packNames(): array
    {
        return array_values(array_filter(array_keys($this->packs), static fn (string $name): bool => $name !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function packConfig(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }
        $pack = $this->packs[$name] ?? [];
        return is_array($pack) ? $pack : [];
    }

    public function withPack(string $name): self
    {
        $overrides = $this->packConfig($name);
        if ($overrides === []) {
            return $this;
        }

        $merged = $this->config;
        foreach ($overrides as $key => $value) {
            $merged[$key] = $value;
        }

        return new self($merged);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function applyResponsesPayload(array $payload): array
    {
        if (!$this->enabled) {
            return $payload;
        }

        $payload = $this->applyTemperature($payload);
        $payload = $this->applyOutputTokens($payload);
        $payload = $this->applyInputLimit($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyTemperature(array $payload): array
    {
        $max = $this->maxTemperature;
        if ($max <= 0.0) {
            return $payload;
        }

        if (!isset($payload['temperature'])) {
            if ($this->defaultTemperature >= 0.0) {
                $payload['temperature'] = $this->defaultTemperature;
            }
            return $payload;
        }

        $value = (float) $payload['temperature'];
        if ($value < 0.0) {
            $value = 0.0;
        }
        if ($value > $max) {
            $value = $max;
        }
        $payload['temperature'] = $value;

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyOutputTokens(array $payload): array
    {
        $max = $this->maxOutputTokens;
        if ($max <= 0) {
            return $payload;
        }

        if (isset($payload['max_output_tokens'])) {
            $value = (int) $payload['max_output_tokens'];
            if ($value <= 0 || $value > $max) {
                $payload['max_output_tokens'] = $max;
            }
            return $payload;
        }

        if ($this->defaultOutputTokens > 0) {
            $payload['max_output_tokens'] = min($this->defaultOutputTokens, $max);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function applyInputLimit(array $payload): array
    {
        $maxChars = $this->maxInputChars;
        if ($maxChars <= 0) {
            return $payload;
        }

        if (!isset($payload['input'])) {
            return $payload;
        }

        $payload['input'] = $this->trimInput($payload['input'], $maxChars);

        return $payload;
    }

    private function trimInput(mixed $input, int $maxChars): mixed
    {
        if ($maxChars <= 0) {
            return $input;
        }

        if (is_string($input)) {
            return $this->truncate($input, $maxChars);
        }

        if (!is_array($input)) {
            return $input;
        }

        $trimmed = [];
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $trimmed[$key] = $this->truncate($value, $maxChars);
                continue;
            }
            if (is_array($value)) {
                $trimmed[$key] = $this->trimInputArray($value, $maxChars);
                continue;
            }
            $trimmed[$key] = $value;
        }

        return $trimmed;
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    private function trimInputArray(array $value, int $maxChars): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $result[$key] = $this->truncate($item, $maxChars);
                continue;
            }
            if (is_array($item)) {
                $result[$key] = $this->trimInputArray($item, $maxChars);
                continue;
            }
            $result[$key] = $item;
        }

        if (isset($result['content']) && is_string($result['content'])) {
            $result['content'] = $this->truncate($result['content'], $maxChars);
        }

        if (isset($result['text']) && is_string($result['text'])) {
            $result['text'] = $this->truncate($result['text'], $maxChars);
        }

        return $result;
    }

    private function truncate(string $value, int $maxChars): string
    {
        if ($maxChars <= 0) {
            return $value;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') <= $maxChars) {
                return $value;
            }
            return mb_substr($value, 0, $maxChars, 'UTF-8');
        }

        if (strlen($value) <= $maxChars) {
            return $value;
        }

        return substr($value, 0, $maxChars);
    }
}

