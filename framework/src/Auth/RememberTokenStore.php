<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use function Fnlla\Support\safe_mkdir;
use RuntimeException;

final class RememberTokenStore
{
    public function __construct(private string $path, private int $ttl = 1209600)
    {
    }

    public function issue(string|int $userId): string
    {
        $this->ensureDirectory();
        $token = bin2hex(random_bytes(32));
        $record = [
            'user_id' => $userId,
            'expires_at' => time() + max(0, $this->ttl),
        ];
        $this->writeRecord($token, $record);
        return $token;
    }

    public function validate(string $token): string|int|null
    {
        if ($token === '') {
            return null;
        }

        $record = $this->readRecord($token);
        if ($record === null) {
            return null;
        }

        $expiresAt = (int) ($record['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt <= time()) {
            $this->forget($token);
            return null;
        }

        return $record['user_id'] ?? null;
    }

    public function forget(string $token): void
    {
        if ($token === '') {
            return;
        }
        $path = $this->pathForToken($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function writeRecord(string $token, array $record): void
    {
        $path = $this->pathForToken($token);
        $json = json_encode($record, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode remember token.');
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist remember token.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRecord(string $token): ?array
    {
        $path = $this->pathForToken($token);
        if (!is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $record = json_decode($raw, true);
        return is_array($record) ? $record : null;
    }

    private function pathForToken(string $token): string
    {
        $hash = hash('sha256', $token);
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function ensureDirectory(): void
    {
        safe_mkdir($this->path, 0755, true, 'auth-remember');
    }
}
