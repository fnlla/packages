<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

use RuntimeException;

final class FileCacheStore
{
    public function __construct(private string $path, private ?int $defaultTtl = null)
    {
        $this->ensureDirectory();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $payload = $this->getPayload($key);
        if ($payload === null) {
            return $default;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expiresAt = $this->expiresAt($ttl);
        $payload = [
            'expires_at' => $expiresAt,
            'value' => base64_encode(serialize($value)),
        ];

        return $this->write($key, $payload);
    }

    public function delete(string $key): bool
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return true;
        }

        return unlink($path);
    }

    public function clear(): bool
    {
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.cache.php') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        return true;
    }

    public function has(string $key): bool
    {
        return $this->getPayload($key) !== null;
    }

    /**
     * @return array{expires_at: int|null, value: mixed}|null
     */
    public function getPayload(string $key): ?array
    {
        $path = $this->pathForKey($key);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return null;
        }

        $expiresAt = $payload['expires_at'] ?? null;
        if ($expiresAt !== null && (int) $expiresAt <= time()) {
            @unlink($path);
            return null;
        }

        $valueRaw = $payload['value'] ?? '';
        if (!is_string($valueRaw)) {
            return null;
        }

        $value = @unserialize(base64_decode($valueRaw, true) ?: '', ['allowed_classes' => false]);
        return [
            'expires_at' => $expiresAt,
            'value' => $value,
        ];
    }

    public function basePath(): string
    {
        return $this->path;
    }

    /**
     * @param array{expires_at: int|null, value: string} $payload
     */
    private function write(string $key, array $payload): bool
    {
        $this->ensureDirectory();
        $path = $this->pathForKey($key);
        $tmp = $path . '.' . uniqid('tmp', true);

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode cache payload.');
        }

        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }

        return rename($tmp, $path);
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        if (!@mkdir($this->path, 0755, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create cache directory: ' . $this->path);
        }
    }

    private function pathForKey(string $key): string
    {
        $hash = sha1($key);
        return $this->path . DIRECTORY_SEPARATOR . $hash . '.cache.php';
    }

    private function expiresAt(?int $ttl): ?int
    {
        $ttl = $ttl ?? $this->defaultTtl;
        if ($ttl === null || $ttl <= 0) {
            return null;
        }

        return time() + $ttl;
    }
}
