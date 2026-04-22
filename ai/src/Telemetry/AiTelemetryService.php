<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Telemetry;

use RuntimeException;

final class AiTelemetryService
{
    private bool $enabled;
    private bool $storeInput;
    private bool $storeOutput;
    private bool $storeContext;
    private bool $storeSources;
    private int $maxChars;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private AiTelemetryRepository $repo,
        array $config = []
    ) {
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->storeInput = (bool) ($config['store_input'] ?? true);
        $this->storeOutput = (bool) ($config['store_output'] ?? true);
        $this->storeContext = (bool) ($config['store_context'] ?? false);
        $this->storeSources = (bool) ($config['store_sources'] ?? false);
        $this->maxChars = (int) ($config['max_chars'] ?? 8000);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     * @param array<string, mixed> $meta
     */
    public function record(array $payload, array $response, array $meta = []): ?int
    {
        if (!$this->enabled) {
            return null;
        }

        $this->repo->ensureSchema();

        $inputText = $this->storeInput ? $this->extractInputText($payload['input'] ?? null) : '';
        $outputText = $this->storeOutput ? $this->extractOutputText($response['data'] ?? []) : '';
        $contextText = $this->storeContext ? (string) ($meta['context'] ?? '') : '';
        $sources = $this->storeSources ? ($meta['sources'] ?? []) : [];

        $inputText = $this->limitChars($inputText);
        $outputText = $this->limitChars($outputText);
        $contextText = $this->limitChars($contextText);

        $now = gmdate('Y-m-d H:i:s');
        $provider = (string) ($meta['provider'] ?? '');
        $model = (string) ($payload['model'] ?? ($meta['model'] ?? ''));
        $temperature = isset($payload['temperature']) ? (float) $payload['temperature'] : null;
        $maxOutputTokens = isset($payload['max_output_tokens']) ? (int) $payload['max_output_tokens'] : null;
        $status = $response['ok'] ? 'ok' : 'error';
        $error = $response['ok'] ? '' : (string) ($response['error'] ?? 'Unknown error');

        $row = [
            'provider' => $provider !== '' ? $provider : null,
            'model' => $model !== '' ? $model : null,
            'status' => $status,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input_text' => $inputText !== '' ? $inputText : null,
            'output_text' => $outputText !== '' ? $outputText : null,
            'context_text' => $contextText !== '' ? $contextText : null,
            'sources' => $sources !== [] ? $this->encodeJson($sources) : null,
            'error' => $error !== '' ? $error : null,
            'meta' => $meta !== [] ? $this->encodeJson($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return $this->repo->insert($row);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function update(int $id, array $meta): void
    {
        if (!$this->enabled) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $payload = [
            'meta' => $meta !== [] ? $this->encodeJson($meta) : null,
            'updated_at' => $now,
        ];

        $this->repo->update($id, $payload);
    }

    private function limitChars(string $value): string
    {
        $max = $this->maxChars;
        if ($max <= 0) {
            return $value;
        }

        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }

    private function extractInputText(mixed $input): string
    {
        if ($input === null) {
            return '';
        }

        if (is_string($input)) {
            return $input;
        }

        $chunks = [];
        $this->collectText($input, $chunks);

        return trim(implode("\n", $chunks));
    }

    private function extractOutputText(mixed $data): string
    {
        if (!is_array($data)) {
            return '';
        }

        $chunks = [];
        $output = $data['output'] ?? null;
        if (is_array($output)) {
            foreach ($output as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $content = $item['content'] ?? null;
                if (is_array($content)) {
                    foreach ($content as $piece) {
                        if (!is_array($piece)) {
                            continue;
                        }
                        $type = (string) ($piece['type'] ?? '');
                        if ($type === 'output_text' || $type === 'text') {
                            $text = (string) ($piece['text'] ?? '');
                            if ($text !== '') {
                                $chunks[] = $text;
                            }
                        }
                    }
                }

                if (isset($item['text']) && is_string($item['text']) && $item['text'] !== '') {
                    $chunks[] = $item['text'];
                }
            }
        }

        if ($chunks === [] && isset($data['output_text']) && is_string($data['output_text'])) {
            $chunks[] = $data['output_text'];
        }

        return trim(implode("\n", $chunks));
    }

    /**
     * @param array<string> $chunks
     */
    private function collectText(mixed $value, array &$chunks): void
    {
        if (is_string($value)) {
            $chunks[] = $value;
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if ($key === 'content' || $key === 'text' || $key === 'input_text') {
                if (is_string($item)) {
                    $chunks[] = $item;
                    continue;
                }
                if (is_array($item)) {
                    $this->collectText($item, $chunks);
                    continue;
                }
            }

            if (is_array($item)) {
                $this->collectText($item, $chunks);
            } elseif (is_string($item)) {
                $chunks[] = $item;
            }
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode JSON value.');
        }

        return $encoded;
    }
}


