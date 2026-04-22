<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Redaction;

final class AiRedactor
{
    private bool $enabled;
    private string $mask;
    /** @var string[] */
    private array $patterns;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->mask = (string) ($config['mask'] ?? '[REDACTED]');
        $patterns = $config['patterns'] ?? $this->defaultPatterns();
        $this->patterns = is_array($patterns) ? array_values(array_filter($patterns, 'is_string')) : $this->defaultPatterns();
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function redactText(string $text): string
    {
        if (!$this->enabled || $text === '') {
            return $text;
        }

        $redacted = $text;
        foreach ($this->patterns as $pattern) {
            $redacted = preg_replace($pattern, $this->mask, $redacted) ?? $redacted;
        }

        return $redacted;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function redactPayload(array $payload): array
    {
        if (!$this->enabled) {
            return $payload;
        }

        return $this->redactArray($payload);
    }

    /**
     * @param array<mixed> $value
     * @return array<mixed>
     */
    public function redactArray(array $value): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $result[$key] = $this->redactText($item);
                continue;
            }
            if (is_array($item)) {
                $result[$key] = $this->redactArray($item);
                continue;
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function defaultPatterns(): array
    {
        return [
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i',
            '/\\bsk-[A-Za-z0-9]{16,}\\b/',
            '/\\b(?:api|secret|token|key)[=:\\s]+[A-Za-z0-9_\\-]{8,}\\b/i',
            '/\\b(?:\\d[ -]*?){13,16}\\b/',
        ];
    }
}


