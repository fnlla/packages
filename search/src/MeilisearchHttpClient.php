<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

use Fnlla\Support\HttpClient;
use RuntimeException;

final class MeilisearchHttpClient implements SearchClientInterface
{
    private HttpClient $http;
    private string $host;
    private string $key;

    public function __construct(string $host, string $key = '', ?HttpClient $http = null)
    {
        $this->host = rtrim(trim($host), '/');
        $this->key = trim($key);
        $this->http = $http ?? new HttpClient();
    }

    public function search(string $index, string $query, array $options = []): array
    {
        $payload = $options;
        $payload['q'] = $query;
        return $this->requestJson('POST', '/indexes/' . rawurlencode($index) . '/search', $payload);
    }

    public function index(string $index): mixed
    {
        return new MeilisearchIndexHttpClient($this, $index);
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>>|array<int, int|string> $payload
     */
    public function requestJson(string $method, string $path, array $payload = []): array
    {
        $method = strtoupper($method);
        $url = $this->buildUrl($path);
        $headers = $this->headers();

        if ($method === 'GET') {
            $response = $this->http->getJson($url, $headers);
            if (!$response['ok']) {
                throw new RuntimeException($this->formatError($method, $path, $response['status'], $response['error']));
            }
            return is_array($response['data']) ? $response['data'] : [];
        }

        if ($method === 'POST') {
            $response = $this->http->postJsonJson($url, $payload, $headers);
            if (!$response['ok']) {
                throw new RuntimeException($this->formatError($method, $path, $response['status'], $response['error']));
            }
            return is_array($response['data']) ? $response['data'] : [];
        }

        // Fallback for other verbs through lower-level request semantics.
        if ($method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            $response = $this->rawRequest($method, $url, $headers, $payload);
            if (!$response['ok']) {
                throw new RuntimeException($this->formatError($method, $path, $response['status'], $response['error']));
            }
            $decoded = json_decode($response['body'], true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('Unsupported Meilisearch HTTP method: ' . $method);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|array<int, array<string, mixed>>|array<int, int|string> $payload
     * @return array{ok: bool, status: int, body: string, error: string}
     */
    private function rawRequest(string $method, string $url, array $headers, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return [
                'ok' => false,
                'status' => 0,
                'body' => '',
                'error' => 'Unable to encode JSON payload',
            ];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init();
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to initialize curl'];
            }
            $formattedHeaders = [];
            foreach ($headers as $name => $value) {
                $formattedHeaders[] = $name . ': ' . $value;
            }
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $formattedHeaders,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => $body,
            ]);
            $result = curl_exec($ch);
            $error = curl_error($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $status = is_int($statusCode) ? $statusCode : 0;
            curl_close($ch);
            if ($result === false) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $error !== '' ? $error : 'HTTP request failed'];
            }
            if (!is_string($result)) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'Unexpected HTTP response body'];
            }
            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => $result,
                'error' => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
            ];
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'timeout' => 30,
                'content' => $body,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        $status = 0;
        $httpResponseHeader = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : [];
        foreach ($httpResponseHeader as $line) {
            if (preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $line, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }
        if ($result === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'HTTP request failed'];
        }
        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $result,
            'error' => $status >= 200 && $status < 300 ? '' : 'HTTP ' . $status,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->key !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->key;
        }
        return $headers;
    }

    private function buildUrl(string $path): string
    {
        return $this->host . '/' . ltrim($path, '/');
    }

    private function formatError(string $method, string $path, int $status, string $error): string
    {
        $statusPart = $status > 0 ? ('HTTP ' . $status) : 'network error';
        $suffix = $error !== '' ? (' - ' . $error) : '';
        return 'Meilisearch request failed (' . strtoupper($method) . ' ' . $path . '): ' . $statusPart . $suffix;
    }
}
