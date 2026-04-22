<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use Fnlla\Support\Psr\Http\Message\ServerRequestInterface;
use Fnlla\Support\Psr\Http\Message\StreamInterface;
use Fnlla\Support\Psr\Http\Message\UriInterface;
use Fnlla\Support\ValidationException;
use Fnlla\Support\Validator;

/**
 * HTTP request wrapper with helpers for headers, input, and trusted proxies.
 *
 * @api
 */
final class Request implements ServerRequestInterface
{
    private string $method;
    private UriInterface $uri;
    private array $headers = [];
    private string $protocol = '1.1';
    private StreamInterface $body;
    private string $requestTarget = '';

    private array $serverParams = [];
    private array $cookieParams = [];
    private array $queryParams = [];
    private array $uploadedFiles = [];
    private mixed $parsedBody = null;
    private array $attributes = [];
    private array $trustedProxyConfig = [];

    public function __construct(
        string $method,
        UriInterface $uri,
        array $headers = [],
        ?StreamInterface $body = null,
        array $serverParams = [],
        array $trustedProxyConfig = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body ?? Stream::fromString();
        $this->serverParams = $serverParams;
        $this->trustedProxyConfig = $trustedProxyConfig;
    }

    public static function fromGlobals(string $basePath = '', array $trustedProxyConfig = []): self
    {
        $method = self::detectMethod();
        $uri = self::detectUri();

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && str_starts_with($uri->getPath(), $basePath)) {
            $path = substr($uri->getPath(), strlen($basePath)) ?: '/';
            $uri = $uri->withPath($path);
        }

        $headers = self::collectHeaders();
        $bodyStream = Stream::fromString(self::getRawBody());

        $request = new self($method, $uri, $headers, $bodyStream, $_SERVER, $trustedProxyConfig);
        $request = $request->withCookieParams($_COOKIE);
        $request = $request->withQueryParams($_GET);
        $request = $request->withUploadedFiles(self::normalizeFiles($_FILES));
        $request = $request->withParsedBody(self::parseBody($request));

        return $request;
    }

    public static function fromPsr(ServerRequestInterface $request, string $basePath = '', array $trustedProxyConfig = []): self
    {
        if ($request instanceof self) {
            $normalized = $request->withTrustedProxyConfig($trustedProxyConfig);
        } else {
            $normalized = new self(
                $request->getMethod(),
                $request->getUri(),
                $request->getHeaders(),
                $request->getBody(),
                $request->getServerParams(),
                $trustedProxyConfig
            );

            $normalized = $normalized->withCookieParams($request->getCookieParams());
            $normalized = $normalized->withQueryParams($request->getQueryParams());
            $normalized = $normalized->withUploadedFiles($request->getUploadedFiles());
            $normalized = $normalized->withParsedBody($request->getParsedBody());

            foreach ($request->getAttributes() as $key => $value) {
                $normalized = $normalized->withAttribute((string) $key, $value);
            }
        }

        $basePath = rtrim($basePath, '/');
        if ($basePath !== '') {
            $uri = $normalized->getUri();
            if (str_starts_with($uri->getPath(), $basePath)) {
                $path = substr($uri->getPath(), strlen($basePath)) ?: '/';
                $normalized = $normalized->withUri($uri->withPath($path));
            }
        }

        return $normalized;
    }

    private function withTrustedProxyConfig(array $trustedProxyConfig): self
    {
        if ($trustedProxyConfig === $this->trustedProxyConfig) {
            return $this;
        }

        $clone = clone $this;
        $clone->trustedProxyConfig = $trustedProxyConfig;
        return $clone;
    }

    private static function detectMethod(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'POST') {
            return $method;
        }

        $override = $_POST['_method'] ?? null;
        if (!is_string($override) || trim($override) === '') {
            $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
        }

        if (is_string($override)) {
            $candidate = strtoupper(trim($override));
            if (in_array($candidate, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $candidate;
            }
        }

        return $method;
    }

    private static function detectUri(): UriInterface
    {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');
        return new Uri($uri);
    }

    private static function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    private static function getRawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    private static function parseBody(self $request): mixed
    {
        if ($request->getMethod() === 'GET') {
            return $request->getQueryParams();
        }

        $raw = (string) $request->getBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            if ($raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            if ($raw === '') {
                return $_POST !== [] ? $_POST : null;
            }
            parse_str($raw, $parsed);
            return is_array($parsed) ? $parsed : null;
        }

        if ($request->getMethod() === 'POST' || strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            return $_POST !== [] ? $_POST : null;
        }

        if ($raw === '') {
            return null;
        }

        return null;
    }

    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['tmp_name'])) {
                if (is_array($value['tmp_name'])) {
                    $normalized[$key] = self::normalizeNestedFiles($value);
                } else {
                    $normalized[$key] = new UploadedFile($value);
                }
                continue;
            }
            $normalized[$key] = self::normalizeFiles($value);
        }
        return $normalized;
    }

    private static function normalizeNestedFiles(array $value): array
    {
        $files = [];
        foreach ($value['tmp_name'] as $index => $tmpName) {
            $files[$index] = new UploadedFile([
                'tmp_name' => $tmpName,
                'name' => $value['name'][$index] ?? '',
                'type' => $value['type'][$index] ?? '',
                'size' => $value['size'][$index] ?? 0,
                'error' => $value['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            ]);
        }
        return $files;
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
        $name = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $name) {
                return true;
            }
        }
        return false;
    }

    public function getHeader(string $name): array
    {
        $name = strtolower($name);
        foreach ($this->headers as $key => $values) {
            if (strtolower($key) === $name) {
                return $values;
            }
        }
        return [];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);
        return implode(',', $values);
    }

    public function withHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $this->normalizeHeaderValue($value);
        return $clone;
    }

    public function withAddedHeader(string $name, string|array $value): self
    {
        $clone = clone $this;
        $existing = $clone->headers[$name] ?? [];
        $clone->headers[$name] = array_merge($existing, $this->normalizeHeaderValue($value));
        return $clone;
    }

    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        foreach ($clone->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                unset($clone->headers[$key]);
            }
        }
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

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target !== '' ? $target : '/';
    }

    public function withRequestTarget(string $requestTarget): self
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): self
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): self
    {
        $clone = clone $this;
        $clone->uri = $uri;
        if (!$preserveHost) {
            $host = $uri->getHost();
            if ($host !== '') {
                $clone->headers['Host'] = [$host];
            }
        }
        return $clone;
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): self
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): self
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): self
    {
        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;
        return $clone;
    }

    public function getParsedBody(): mixed
    {
        return $this->parsedBody;
    }

    public function withParsedBody(mixed $data): self
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): self
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    public function withParams(array $params): self
    {
        $clone = clone $this;
        foreach ($params as $key => $value) {
            $clone->attributes[$key] = $value;
        }
        return $clone;
    }

    public function all(): array
    {
        $body = $this->getParsedBody();
        return is_array($body) ? $body : [];
    }

    public function allInput(): array
    {
        $body = $this->all();
        return array_merge($this->queryParams, $body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->all();
        if (array_key_exists($key, $body)) {
            return $body[$key];
        }
        if (array_key_exists($key, $this->queryParams)) {
            return $this->queryParams[$key];
        }
        return $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $values = $this->getHeader($name);
        if ($values === []) {
            return $default;
        }
        return implode(',', $values);
    }

    public function wantsJson(): bool
    {
        if ($this->prefersJson()) {
            return true;
        }

        $contentType = strtolower((string) $this->getHeaderLine('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        if ($this->isAjax() && !$this->prefersHtml()) {
            return true;
        }

        return str_starts_with($this->uri->getPath(), '/api');
    }

    public function validate(array $rules, array $messages = [], string $bag = 'default'): array
    {
        $input = $this->allInput();
        if ($this->uploadedFiles !== []) {
            $input = array_merge($input, $this->uploadedFiles);
        }

        $validator = Validator::make($input, $rules, $messages);
        if (!$validator->passes()) {
            throw new ValidationException($validator->errors(), 'Validation failed.', 422, $input, $bag);
        }
        return $validator->validated();
    }

    public function file(string $key): ?UploadedFile
    {
        $file = $this->uploadedFiles[$key] ?? null;
        return $file instanceof UploadedFile ? $file : null;
    }

    public function clientIp(): string
    {
        $remote = (string) ($this->serverParams['REMOTE_ADDR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote === '') {
            return '';
        }

        if (!$this->isTrustedProxy($remote)) {
            return $remote;
        }

        if (!$this->isHeaderTrusted('x-forwarded-for')) {
            return $remote;
        }

        $forwarded = $this->getHeaderLine('X-Forwarded-For');
        if ($forwarded === '') {
            return $remote;
        }

        $parts = array_map('trim', explode(',', $forwarded));
        foreach ($parts as $part) {
            if ($part !== '' && strtolower($part) !== 'unknown') {
                return $part;
            }
        }

        return $remote;
    }

    public function isSecure(): bool
    {
        $remote = (string) ($this->serverParams['REMOTE_ADDR'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote !== '' && $this->isTrustedProxy($remote) && $this->isHeaderTrusted('x-forwarded-proto')) {
            $proto = $this->getHeaderLine('X-Forwarded-Proto');
            if ($proto !== '') {
                $first = strtolower(trim(explode(',', $proto)[0]));
                if ($first !== '') {
                    return $first === 'https';
                }
            }
        }

        $https = $this->serverParams['HTTPS'] ?? ($_SERVER['HTTPS'] ?? null);
        if (is_string($https)) {
            return strtolower($https) !== 'off' && $https !== '';
        }
        if ($https === true) {
            return true;
        }

        return strtolower($this->uri->getScheme()) === 'https';
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[$name] = $this->normalizeHeaderValue($value);
        }
        return $normalized;
    }

    private function normalizeHeaderValue(string|array $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('strval', $value));
        }
        return [ (string) $value ];
    }

    private function isTrustedProxy(string $remote): bool
    {
        $config = $this->trustedProxyConfig();
        $proxies = $config['proxies'] ?? [];

        if (is_string($proxies)) {
            $proxies = trim($proxies);
            if ($proxies === '*' || strtolower($proxies) === 'all') {
                return true;
            }
            $proxies = $proxies === '' ? [] : array_map('trim', explode(',', $proxies));
        }

        if (is_array($proxies)) {
            if ($proxies === ['*']) {
                return true;
            }
            return in_array($remote, $proxies, true);
        }

        return false;
    }

    private function isHeaderTrusted(string $header): bool
    {
        $config = $this->trustedProxyConfig();
        $headers = $config['headers'] ?? [];
        $default = ['x-forwarded-for', 'x-forwarded-proto'];

        if (is_string($headers)) {
            $headers = $headers === '' ? [] : array_map('trim', explode(',', $headers));
        }

        if (!is_array($headers) || $headers === []) {
            $headers = $default;
        }

        $headers = array_map(fn ($item) => strtolower((string) $item), $headers);
        return in_array(strtolower($header), $headers, true);
    }

    private function trustedProxyConfig(): array
    {
        return $this->trustedProxyConfig;
    }

    private function prefersJson(): bool
    {
        $accept = strtolower((string) $this->getHeaderLine('Accept'));
        if ($accept === '') {
            return false;
        }

        $parsed = $this->parseAcceptHeader($accept);
        $jsonQ = $this->acceptQuality($parsed, static fn (string $type): bool => $type === 'application/json' || str_contains($type, '+json'));
        $htmlQ = $this->acceptQuality($parsed, static fn (string $type): bool => $type === 'text/html' || $type === 'application/xhtml+xml');

        if ($jsonQ <= 0.0) {
            return false;
        }

        if ($htmlQ <= 0.0) {
            return true;
        }

        return $jsonQ > $htmlQ;
    }

    private function prefersHtml(): bool
    {
        $accept = strtolower((string) $this->getHeaderLine('Accept'));
        if ($accept === '') {
            return false;
        }

        $parsed = $this->parseAcceptHeader($accept);
        $htmlQ = $this->acceptQuality($parsed, static fn (string $type): bool => $type === 'text/html' || $type === 'application/xhtml+xml');
        $jsonQ = $this->acceptQuality($parsed, static fn (string $type): bool => $type === 'application/json' || str_contains($type, '+json'));

        if ($htmlQ <= 0.0) {
            return false;
        }

        if ($jsonQ <= 0.0) {
            return true;
        }

        return $htmlQ >= $jsonQ;
    }

    private function isAjax(): bool
    {
        return strtolower((string) $this->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';
    }

    private function parseAcceptHeader(string $header): array
    {
        $parts = array_map('trim', explode(',', $header));
        $parsed = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $segments = array_map('trim', explode(';', $part));
            $type = strtolower(array_shift($segments) ?? '');
            if ($type === '') {
                continue;
            }

            $q = 1.0;
            foreach ($segments as $segment) {
                if (str_starts_with($segment, 'q=')) {
                    $value = substr($segment, 2);
                    if ($value !== '' && is_numeric($value)) {
                        $q = max(0.0, min(1.0, (float) $value));
                    }
                }
            }

            $parsed[] = ['type' => $type, 'q' => $q];
        }

        return $parsed;
    }

    private function acceptQuality(array $parsed, callable $matcher): float
    {
        $best = 0.0;
        foreach ($parsed as $item) {
            $type = $item['type'] ?? '';
            $q = (float) ($item['q'] ?? 0.0);
            if ($q <= 0.0) {
                continue;
            }
            if ($matcher($type)) {
                $best = max($best, $q);
            }
        }
        return $best;
    }
}






