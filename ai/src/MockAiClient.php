<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai;

final class MockAiClient implements AiClientInterface
{
    public function responses(array $payload): array
    {
        $text = $this->buildMockResponse($payload);

        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'output' => [
                    [
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => $text,
                            ],
                        ],
                    ],
                ],
                'output_text' => $text,
            ],
            'error' => '',
        ];
    }

    public function embeddings(array $payload): array
    {
        $input = $payload['input'] ?? '';
        $seed = is_string($input) ? $input : json_encode($input);
        if ($seed === false) {
            $seed = '';
        }
        $embedding = $this->mockEmbedding($seed);

        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'data' => [
                    [
                        'embedding' => $embedding,
                    ],
                ],
            ],
            'error' => '',
        ];
    }

    public function models(): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'data' => [
                    ['id' => 'mock-model'],
                ],
            ],
            'error' => '',
        ];
    }

    public function realtimeClientSecret(array $payload = []): array
    {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'value' => 'mock-realtime-token',
            ],
            'error' => '',
        ];
    }

    private function buildMockResponse(array $payload): string
    {
        $instructions = (string) ($payload['instructions'] ?? '');
        $inputText = $this->extractInputText($payload['input'] ?? null);
        $combined = trim($instructions . "\n" . $inputText);

        if (stripos($combined, 'Return only valid JSON') !== false) {
            $schema = $this->extractSchema($combined);
            if ($schema !== '') {
                return $this->mockJsonFromSchema($schema);
            }

            return json_encode([
                'title' => 'Mock Response',
                'summary' => 'Offline mock response. Provide provider credentials for real output.',
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        return "Offline mock response. Provide provider credentials for real output.";
    }

    private function extractSchema(string $text): string
    {
        $pos = stripos($text, 'Output JSON schema:');
        if ($pos === false) {
            return '';
        }

        return trim(substr($text, $pos + strlen('Output JSON schema:')));
    }

    private function mockJsonFromSchema(string $schema): string
    {
        $lines = preg_split('/\r?\n/', $schema) ?: [];
        $depth = 0;
        $data = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $depth += substr_count($line, '{') - substr_count($line, '}');
                continue;
            }

            if ($depth === 1 && preg_match('/^\"([A-Za-z0-9_]+)\"/', $trim, $matches)) {
                $key = $matches[1];
                $data[$key] = $this->mockValueForLine($key, $trim);
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');
        }

        if ($data === []) {
            $data = [
                'title' => 'Mock Response',
                'summary' => 'Offline mock response. Provide provider credentials for real output.',
            ];
        }

        if (isset($data['action_plan']) && is_array($data['action_plan'])) {
            $data['action_plan'] = $this->defaultPlan();
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @return array<int, float>
     */
    private function mockEmbedding(string $seed): array
    {
        $hash = sha1($seed);
        $values = [];
        for ($i = 0; $i < 8; $i++) {
            $chunk = substr($hash, $i * 5, 5);
            $num = hexdec($chunk) % 1000;
            $values[] = ($num / 1000.0) - 0.5;
        }

        return $values;
    }

    private function mockValueForLine(string $key, string $line): mixed
    {
        if (preg_match('/:\s*\[/', $line)) {
            return [];
        }
        if (preg_match('/:\s*\{/', $line)) {
            return (object) [];
        }

        if (str_contains($key, 'score')) {
            return 50;
        }
        if ($key === 'status') {
            return 'warn';
        }
        if ($key === 'severity') {
            return 'medium';
        }
        if ($key === 'impact') {
            return 'medium';
        }
        if ($key === 'forecast') {
            return 'on_track';
        }

        return '...';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function defaultPlan(): array
    {
        return [
            'modules' => [],
            'milestones' => [],
            'tasks' => [],
            'bugs' => [],
            'tech_debt' => [],
            'adrs' => [],
            'documents' => [],
        ];
    }

    private function extractInputText(mixed $input): string
    {
        if (is_string($input)) {
            return $input;
        }
        if (!is_array($input)) {
            return '';
        }

        $chunks = [];
        foreach ($input as $item) {
            if (is_array($item) && isset($item['content'])) {
                $content = $item['content'];
                if (is_string($content)) {
                    $chunks[] = $content;
                } elseif (is_array($content)) {
                    foreach ($content as $part) {
                        if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                            $chunks[] = $part['text'];
                        }
                    }
                }
            }
        }

        return trim(implode("\n", $chunks));
    }
}


