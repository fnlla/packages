<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Contracts\Cache\CacheStoreInterface;

class FileStore implements CacheStoreInterface
{
    public function __construct(private string $path)
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
        safe_mkdir($this->path, 0755, true, 'cache');
    }

    public function get(string $key): mixed
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if ($payload === false && $raw !== 'b:0;') {
            safe_unlink($file, 'cache');
            return null;
        }
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);
        if ($expiresAt !== 0 && $expiresAt < time()) {
            safe_unlink($file, 'cache');
            return null;
        }

        return $payload['value'];
    }

    public function put(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $expiresAt = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $payload = serialize([
            'value' => $value,
            'expires_at' => $expiresAt,
        ]);
        file_put_contents($this->filePath($key), $payload, LOCK_EX);
    }

    public function forget(string $key): void
    {
        safe_unlink($this->filePath($key), 'cache');
    }

    public function clear(): void
    {
        if (!is_dir($this->path)) {
            return;
        }
        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*.cache') as $file) {
            safe_unlink($file, 'cache');
        }
    }

    private function filePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->path . DIRECTORY_SEPARATOR . $hash . '.cache';
    }
}





