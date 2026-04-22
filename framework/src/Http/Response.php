<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Support\Psr\Http\Message\StreamInterface;
use Fnlla\Runtime\RequestContext;

/**
 * HTTP response with header management and output helpers.
 *
 * @api
 */
final class Response implements ResponseInterface
{
    private int $status;
    private string $reasonPhrase;
    private array $headers = [];
    private array $headerMap = [];
    private string $protocol = '1.1';
    private StreamInterface $body;

    private static array $phrases = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Server Error',
        503 => 'Service Unavailable',
    ];

    public function __construct(
        int $status = 200,
        array $headers = [],
        ?StreamInterface $body = null,
        string $reasonPhrase = ''
    ) {
        $this->status = $status;
        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::$phrases[$status] ?? '');
        $this->headers = [];
        $this->headerMap = [];
        $this->setHeaders($headers);
        $this->body = $body ?? Stream::fromString();
    }

    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    public function withProtocolVersion(string $version): self
    {
        $clone = clone $this;
        $clone->protocol = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        $key = strtolower($name);
        return array_key_exists($key, $this->headerMap);
    }

    public function getHeader(string $name): array
    {
        $key = strtolower($name);
        return $this->headerMap[$key][1] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);
        return implode(',', $values);
    }

    public function withHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->setHeader($name, $this->normalizeHeaderValue($value));
        return $clone;
    }

    public function withAddedHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->addHeader($name, $this->normalizeHeaderValue($value));
        return $clone;
    }

    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): self
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->setHeaders($headers);
        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): self
    {
        $clone = clone $this;
        $clone->status = $code;
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::$phrases[$code] ?? '');
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers);
        return new self($status, $headers, Stream::fromString($body));
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/json; charset=utf-8'], $headers);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        return new self($status, $headers, Stream::fromString($payload));
    }

    public static function xml(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'application/xml; charset=utf-8'], $headers);
        return new self($status, $headers, Stream::fromString($body));
    }

    public static function text(string $body, int $status = 200, array $headers = []): self
    {
        $headers = array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers);
        return new self($status, $headers, Stream::fromString($body));
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], Stream::fromString(''));
    }

    public function withInput(array $input): self
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return $this;
        }

        if (array_key_exists('_token', $input)) {
            unset($input['_token']);
        }
        if (array_key_exists('_method', $input)) {
            unset($input['_method']);
        }

        $clean = $this->sanitizeInput($input);
        if (method_exists($session, 'flash')) {
            $session->flash('_Fnlla_old', $clean);
        } else {
            $session->put('_Fnlla_old', $clean);
        }

        return $this;
    }

    public function withErrors(array $errors, string $bag = 'default'): self
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return $this;
        }

        if (method_exists($session, 'flash')) {
            $session->flash('_Fnlla_errors', $errors);
            $session->flash('_Fnlla_error_bag', $bag);
        } else {
            $session->put('_Fnlla_errors', $errors);
            $session->put('_Fnlla_error_bag', $bag);
        }

        return $this;
    }

    public static function stream(callable $callback, int $status = 200, array $headers = []): self
    {
        $body = Stream::fromString('');
        return new self($status, $headers, $body->withCallback($callback));
    }

    public static function file(string $path, ?string $downloadName = null, array $headers = []): self
    {
        if (!is_file($path)) {
            return self::text('File not found', 404);
        }

        $size = filesize($path);
        $mime = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    $mime = $detected;
                }
                finfo_close($finfo);
            }
        }

        $disposition = $downloadName !== null
            ? 'attachment; filename="' . addslashes($downloadName) . '"'
            : 'inline';

        $headers = array_merge([
            'Content-Type' => $mime,
            'Content-Length' => (string) ($size !== false ? $size : 0),
            'Content-Disposition' => $disposition,
        ], $headers);

        $resource = fopen($path, 'rb');
        if ($resource === false) {
            return self::text('File not readable', 404);
        }

        return new self(200, $headers, new Stream($resource));
    }

    public static function download(string $path, ?string $name = null, array $headers = []): self
    {
        $name = $name ?? basename($path);
        return self::file($path, $name, $headers);
    }

    public function withBasePath(string $basePath): self
    {
        $basePath = '/' . ltrim(rtrim($basePath, '/'), '/');
        $contentType = $this->getHeaderLine('Content-Type');
        if ($basePath === '/' || $contentType === '' || !str_contains(strtolower($contentType), 'html')) {
            return $this;
        }

        $body = (string) $this->body;
        $updated = preg_replace_callback(
            '/\\b(href|src|action)=([\"\\\'])\\/(?!\\/)([^\"\\\'\\s>]+)/i',
            function (array $matches) use ($basePath) {
                $path = '/' . $matches[3];
                if (str_starts_with($path, $basePath . '/')) {
                    return $matches[0];
                }
                return $matches[1] . '=' . $matches[2] . $basePath . $path;
            },
            $body
        ) ?: $body;

        return $this->withBody(Stream::fromString($updated));
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            $context = RequestContext::current();
            if ($context instanceof RequestContext) {
                if ($context->includeRequestIdHeader() && !$this->hasHeader('X-Request-Id')) {
                    $requestId = $context->requestId();
                    if ($requestId !== '') {
                        $this->setHeader('X-Request-Id', [$requestId]);
                    }
                }
                if ($context->includeTraceIdHeader() && !$this->hasHeader('X-Trace-Id')) {
                    $traceId = $context->traceId();
                    if ($traceId !== '') {
                        $this->setHeader('X-Trace-Id', [$traceId]);
                    }
                }
                if ($context->includeSpanIdHeader() && !$this->hasHeader('X-Span-Id')) {
                    $spanId = $context->spanId();
                    if ($spanId !== '') {
                        $this->setHeader('X-Span-Id', [$spanId]);
                    }
                }
            }
            if (!$this->hasHeader('X-Fnlla')) {
                $this->setHeader('X-Fnlla', ['yes']);
            }
            foreach ($this->headers as $name => $values) {
                $name = $this->sanitizeHeaderName($name);
                foreach ($values as $value) {
                    $value = $this->sanitizeHeaderValue($value);
                    if ($name !== '') {
                        header($name . ': ' . $value, false);
                    }
                }
            }
        }

        $body = $this->body;
        if ($body instanceof Stream && $body->hasCallback()) {
            $body->invokeCallback();
            return;
        }

        echo (string) $body;
    }

    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $this->normalizeHeaderValue($value));
        }
    }

    private function normalizeHeaderValue(string|array $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }
        return [ (string) $value ];
    }

    private function setHeader(string $name, array $values): void
    {
        $key = strtolower($name);
        if (isset($this->headerMap[$key])) {
            $existingName = $this->headerMap[$key][0];
            if ($existingName !== $name) {
                unset($this->headers[$existingName]);
            }
        }
        $this->headerMap[$key] = [$name, $values];
        $this->headers[$name] = $values;
    }

    private function addHeader(string $name, array $values): void
    {
        $key = strtolower($name);
        if (isset($this->headerMap[$key])) {
            [$originalName, $existing] = $this->headerMap[$key];
            $merged = array_merge($existing, $values);
            $this->headerMap[$key] = [$originalName, $merged];
            $this->headers[$originalName] = $merged;
            return;
        }

        $this->setHeader($name, $values);
    }

    private function removeHeader(string $name): void
    {
        $key = strtolower($name);
        if (!isset($this->headerMap[$key])) {
            return;
        }
        $originalName = $this->headerMap[$key][0];
        unset($this->headerMap[$key], $this->headers[$originalName]);
    }

    private function sanitizeHeaderName(string $name): string
    {
        $name = str_replace(["\r", "\n"], '', $name);
        return trim($name);
    }

    private function sanitizeHeaderValue(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }

    private function resolveSession(): ?\Fnlla\Session\SessionInterface
    {
        if (!interface_exists(\Fnlla\Session\SessionInterface::class)) {
            return null;
        }

        $app = $GLOBALS['Fnlla_app'] ?? null;
        if (!$app instanceof \Fnlla\Core\Container) {
            return null;
        }

        if (!$app->has(\Fnlla\Session\SessionInterface::class)) {
            return null;
        }

        try {
            $session = $app->make(\Fnlla\Session\SessionInterface::class);
        } catch (\Throwable) {
            return null;
        }

        return $session instanceof \Fnlla\Session\SessionInterface ? $session : null;
    }

    private function sanitizeInput(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $value) {
            if ($value instanceof \Fnlla\Http\UploadedFile) {
                continue;
            }
            if (is_object($value)) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitizeInput($value);
                continue;
            }
            $clean[$key] = $value;
        }
        return $clean;
    }
}





