<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

final class HttpClient
{
    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function getJson(string $url, array $headers = []): array
    {
        $response = $this->request('GET', $url, $headers);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => $response['error'],
            ];
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => 'Invalid JSON response',
            ];
        }

        return [
            'ok' => true,
            'status' => $response['status'],
            'data' => $data,
            'error' => '',
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, body: string, error: string}
     */
    public function postForm(string $url, array $data, array $headers = []): array
    {
        $payload = http_build_query($data);
        $headers = array_merge(['Content-Type' => 'application/x-www-form-urlencoded'], $headers);
        return $this->request('POST', $url, $headers, $payload);
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function postFormJson(string $url, array $data, array $headers = []): array
    {
        $response = $this->postForm($url, $data, $headers);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => $response['error'],
            ];
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => 'Invalid JSON response',
            ];
        }

        return [
            'ok' => true,
            'status' => $response['status'],
            'data' => $decoded,
            'error' => '',
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, body: string, error: string}
     */
    public function postJson(string $url, array $data, array $headers = []): array
    {
        $payload = json_encode($data);
        if ($payload === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'error' => 'Unable to encode JSON payload',
            ];
        }

        $headers = array_merge(['Content-Type' => 'application/json'], $headers);
        return $this->request('POST', $url, $headers, $payload);
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function postJsonJson(string $url, array $data, array $headers = []): array
    {
        $response = $this->postJson($url, $data, $headers);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => $response['error'],
            ];
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'status' => $response['status'],
                'data' => null,
                'error' => 'Invalid JSON response',
            ];
        }

        return [
            'ok' => true,
            'status' => $response['status'],
            'data' => $decoded,
            'error' => '',
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array{ok: bool, status: int, body: string, error: string}
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $attempts = $this->resolveRetries($method);
        $delayMs = $this->resolveRetryDelayMs();
        $attempt = 0;

        do {
            $response = $this->performRequest($method, $url, $headers, $body);
            if ($response['ok'] || !$this->shouldRetry($method, $response)) {
                return $response;
            }

            $attempt++;
            if ($attempt > $attempts) {
                return $response;
            }

            $sleepMs = (int) ($delayMs * (2 ** ($attempt - 1)));
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        } while (true);
    }

    private function performRequest(string $method, string $url, array $headers, ?string $body = null): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch === false) {
                return [
                    'ok' => false,
                    'status' => 0,
                    'body' => '',
                    'error' => 'Unable to initialize curl',
                ];
            }

            $formatted = [];
            foreach ($headers as $key => $value) {
                $formatted[] = $key . ': ' . $value;
            }

            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $formatted,
                CURLOPT_TIMEOUT => 30,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }

            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($body === false) {
                return [
                    'ok' => false,
                    'status' => $status,
                    'body' => '',
                    'error' => $error !== '' ? $error : 'HTTP request failed',
                ];
            }

            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => (string) $body,
                'error' => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
            ];
        }

        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $headerLines),
                'timeout' => 30,
                'content' => $body ?? '',
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        $httpResponseHeader = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : [];
        if (is_array($httpResponseHeader)) {
            foreach ($httpResponseHeader as $line) {
                if (preg_match('/HTTP\\/\\d+\\.\\d+\\s+(\\d+)/', $line, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => '',
                'error' => 'HTTP request failed',
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => (string) $body,
            'error' => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
        ];
    }

    private function resolveRetries(string $method): int
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return 0;
        }

        $value = getenv('HTTP_CLIENT_RETRIES');
        if ($value === false || $value === '') {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function resolveRetryDelayMs(): int
    {
        $value = getenv('HTTP_CLIENT_RETRY_DELAY_MS');
        if ($value === false || $value === '') {
            return 200;
        }

        return max(0, (int) $value);
    }

    private function shouldRetry(string $method, array $response): bool
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return false;
        }

        $status = (int) ($response['status'] ?? 0);
        if ($status === 0) {
            return true;
        }

        return in_array($status, [408, 429, 500, 502, 503, 504], true);
    }
}
