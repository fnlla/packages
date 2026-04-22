<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Session;

use RuntimeException;
use Fnlla\Support\Env;
use Fnlla\Http\Request;

final class FileSessionStore implements SessionInterface
{
    private array $data = [];
    private bool $started = false;
    private string $id = '';

    private string $cookieName;
    private string $cookiePath;
    private string $cookieDomain;
    private bool $cookieSecure;
    private bool $cookieSecureExplicit = false;
    private bool $cookieHttpOnly;
    private string $cookieSameSite;
    private bool $lockFiles;
    private int $gcProbability;

    public function __construct(
        private string $path,
        private int $ttl = 7200,
        array $cookie = [],
        bool $lockFiles = true,
        int $gcProbability = 1
    ) {
        $this->cookieName = (string) ($cookie['name'] ?? 'Fnlla_session');
        $this->cookiePath = (string) ($cookie['path'] ?? '/');
        $this->cookieDomain = (string) ($cookie['domain'] ?? '');
        $this->cookieSecureExplicit = array_key_exists('secure', $cookie);
        $this->cookieSecure = $this->cookieSecureExplicit
            ? (bool) $cookie['secure']
            : $this->defaultSecure();
        $this->cookieHttpOnly = (bool) ($cookie['httponly'] ?? true);
        $this->cookieSameSite = $this->normalizeSameSite((string) ($cookie['samesite'] ?? 'Lax'));
        if ($this->cookieSameSite === 'None') {
            $this->cookieSecure = true;
        }
        $this->lockFiles = $lockFiles;
        $this->gcProbability = max(0, min(100, $gcProbability));
    }

    public function start(?string $id = null): void
    {
        if ($this->started) {
            return;
        }

        $this->ensureDirectory();
        $this->maybeGc();

        $this->id = $this->normalizeId((string) ($id ?? ''));
        if ($this->id === '') {
            $this->id = $this->generateId();
        }

        $this->load();
        $this->ageFlashData();
        $this->started = true;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function applyRequestContext(Request $request): void
    {
        if ($this->cookieSecureExplicit) {
            return;
        }

        $secure = $request->isSecure();
        if (is_bool($secure)) {
            $this->cookieSecure = $secure;
        }
        if ($this->cookieSameSite === 'None') {
            $this->cookieSecure = true;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function all(): array
    {
        return $this->data;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $new = $this->flashKeys('new');
        if (!in_array($key, $new, true)) {
            $new[] = $key;
            $this->setFlashKeys('new', $new);
        }
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function regenerateId(bool $deleteOld = true): void
    {
        if (!$this->started) {
            $this->start();
        }

        $oldId = $this->id;
        $this->id = $this->generateId();
        $this->save();

        if ($deleteOld && $oldId !== '' && $oldId !== $this->id) {
            @unlink($this->pathForId($oldId));
        }
    }

    public function save(): void
    {
        if (!$this->started) {
            $this->start();
        }

        $payload = [
            'expires_at' => time() + max(0, $this->ttl),
            'data' => base64_encode(serialize($this->data)),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Unable to encode session payload.');
        }

        $path = $this->pathForId($this->id);
        $tmp = $path . '.' . uniqid('tmp', true);

        $this->withLock(function () use ($tmp, $json, $path): void {
            if (file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write session file.');
            }

            if (!rename($tmp, $path)) {
                @unlink($tmp);
                throw new RuntimeException('Unable to move session file into place.');
            }
        });
    }

    public function cookieHeader(): string
    {
        $parts = [rawurlencode($this->cookieName) . '=' . rawurlencode($this->id)];

        if ($this->ttl > 0) {
            $parts[] = 'Max-Age=' . $this->ttl;
        }
        if ($this->cookiePath !== '') {
            $parts[] = 'Path=' . $this->cookiePath;
        }
        if ($this->cookieDomain !== '') {
            $parts[] = 'Domain=' . $this->cookieDomain;
        }
        if ($this->cookieSecure) {
            $parts[] = 'Secure';
        }
        if ($this->cookieHttpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->cookieSameSite !== '') {
            $parts[] = 'SameSite=' . $this->cookieSameSite;
        }

        return implode('; ', $parts);
    }

    public function cookieName(): string
    {
        return $this->cookieName;
    }

    private function load(): void
    {
        $path = $this->pathForId($this->id);
        $this->data = $this->withLock(function () use ($path): array {
            if (!is_file($path)) {
                return [];
            }

            $raw = file_get_contents($path);
            if ($raw === false || $raw === '') {
                return [];
            }

            $payload = json_decode($raw, true);
            if (!is_array($payload)) {
                return [];
            }

            $expiresAt = (int) ($payload['expires_at'] ?? 0);
            if ($expiresAt > 0 && $expiresAt <= time()) {
                @unlink($path);
                return [];
            }

            $dataRaw = $payload['data'] ?? '';
            if (!is_string($dataRaw)) {
                return [];
            }

            $decoded = base64_decode($dataRaw, true);
            if (!is_string($decoded)) {
                return [];
            }

            $data = @unserialize($decoded, ['allowed_classes' => false]);
            return is_array($data) ? $data : [];
        });
    }

    private function ensureDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        if (!@mkdir($this->path, 0755, true) && !is_dir($this->path)) {
            throw new RuntimeException('Unable to create session directory: ' . $this->path);
        }
    }

    private function pathForId(string $id): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $id . '.session';
    }

    private function normalizeId(string $id): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9]/', '', $id) ?? '';
        return $normalized;
    }

    private function generateId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return bin2hex(uniqid('session', true));
        }
    }

    private function defaultSecure(): bool
    {
        $env = (string) Env::get('APP_ENV', '');
        if ($env === '') {
            return false;
        }

        return strtolower($env) === 'prod';
    }

    private function normalizeSameSite(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $normalized = ucfirst(strtolower($value));
        $allowed = ['Lax', 'Strict', 'None'];
        if (!in_array($normalized, $allowed, true)) {
            throw new RuntimeException('Invalid SameSite value: ' . $value);
        }

        return $normalized;
    }

    private function flashKeys(string $bucket): array
    {
        $key = $bucket === 'old' ? '_Fnlla_flash_old' : '_Fnlla_flash_new';
        $value = $this->data[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    private function setFlashKeys(string $bucket, array $keys): void
    {
        $key = $bucket === 'old' ? '_Fnlla_flash_old' : '_Fnlla_flash_new';
        $this->data[$key] = array_values(array_unique($keys));
    }

    private function ageFlashData(): void
    {
        $old = $this->flashKeys('old');
        foreach ($old as $key) {
            unset($this->data[$key]);
        }

        $new = $this->flashKeys('new');
        $this->setFlashKeys('old', $new);
        $this->setFlashKeys('new', []);
    }

    private function withLock(callable $callback): mixed
    {
        if (!$this->lockFiles) {
            return $callback();
        }

        $lockPath = $this->path . DIRECTORY_SEPARATOR . '.Fnlla_session.lock';
        $handle = fopen($lockPath, 'c');
        if ($handle === false) {
            return $callback();
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $callback();
            }
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function maybeGc(): void
    {
        if ($this->gcProbability <= 0) {
            return;
        }

        $roll = random_int(1, 100);
        if ($roll > $this->gcProbability) {
            return;
        }

        $this->withLock(function (): void {
            $pattern = $this->path . DIRECTORY_SEPARATOR . '*.session';
            foreach (glob($pattern) ?: [] as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $raw = file_get_contents($file);
                if ($raw === false || $raw === '') {
                    continue;
                }
                $payload = json_decode($raw, true);
                if (!is_array($payload)) {
                    continue;
                }
                $expiresAt = (int) ($payload['expires_at'] ?? 0);
                if ($expiresAt > 0 && $expiresAt <= time()) {
                    @unlink($file);
                }
            }
        });
    }
}
