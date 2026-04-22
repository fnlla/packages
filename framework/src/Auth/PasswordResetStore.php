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

final class PasswordResetStore
{
    public function __construct(private string $path, private int $ttl = 3600)
    {
    }

    public function issue(string|int $userId): string
    {
        $this->ensureDirectory();
        $token = bin2hex(random_bytes(32));
        $record = [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => time() + max(0, $this->ttl),
        ];
        $this->writeRecord($userId, $record);
        return $token;
    }

    public function validate(string|int $userId, string $token): bool
    {
        $record = $this->readRecord($userId);
        if ($record === null) {
            return false;
        }
        $expiresAt = (int) ($record['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt <= time()) {
            $this->forget($userId);
            return false;
        }
        $hash = (string) ($record['token_hash'] ?? '');
        return $hash !== '' && hash_equals($hash, hash('sha256', $token));
    }

    public function consume(string|int $userId, string $token): bool
    {
        $valid = $this->validate($userId, $token);
        if ($valid) {
            $this->forget($userId);
        }
        return $valid;
    }

    public function forget(string|int $userId): void
    {
        $path = $this->pathForUser($userId);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    private function writeRecord(string|int $userId, array $record): void
    {
        $path = $this->pathForUser($userId);
        $json = json_encode($record, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode reset token.');
        }
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to persist reset token.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readRecord(string|int $userId): ?array
    {
        $path = $this->pathForUser($userId);
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

    private function pathForUser(string|int $userId): string
    {
        $name = hash('sha256', (string) $userId);
        return rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function ensureDirectory(): void
    {
        safe_mkdir($this->path, 0755, true, 'auth-reset');
    }
}
