<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Session;

final class SessionManager implements SessionInterface
{
    private bool $started = false;

    public function __construct(private array $config = [])
    {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        if (isset($this->config['name'])) {
            session_name((string) $this->config['name']);
        }
        $this->configureSavePath();
        if (isset($this->config['cookie'])) {
            $cookie = $this->config['cookie'];
            if (is_array($cookie)) {
                $sameSite = $this->normalizeSameSite((string) ($cookie['samesite'] ?? 'Lax'));
                $secure = (bool) ($cookie['secure'] ?? false);
                if ($sameSite === 'None') {
                    $secure = true;
                }
                session_set_cookie_params([
                    'lifetime' => (int) ($cookie['lifetime'] ?? 0),
                    'path' => (string) ($cookie['path'] ?? '/'),
                    'domain' => (string) ($cookie['domain'] ?? ''),
                    'secure' => $secure,
                    'httponly' => (bool) ($cookie['httponly'] ?? true),
                    'samesite' => $sameSite,
                ]);
            }
        }

        session_start();
        $this->started = true;
    }

    private function configureSavePath(): void
    {
        $configured = $this->config['save_path'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            $path = trim($configured);
            if (\Fnlla\Support\safe_mkdir($path, 0755, true, 'session-save-path') && is_writable($path)) {
                session_save_path($path);
                return;
            }
        }

        $current = (string) session_save_path();
        if ($this->isWritableDirectory($current)) {
            return;
        }

        $fallback = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'fnlla-sessions';

        if (\Fnlla\Support\safe_mkdir($fallback, 0755, true, 'session-fallback-path') && is_writable($fallback)) {
            session_save_path($fallback);
        }
    }

    private function isWritableDirectory(string $path): bool
    {
        $path = trim($path);
        if ($path === '') {
            return false;
        }

        $segments = explode(';', $path);
        $directory = trim((string) end($segments));
        if ($directory === '') {
            return false;
        }

        return is_dir($directory) && is_writable($directory);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    public function regenerateId(bool $deleteOld = true): void
    {
        $this->start();
        session_regenerate_id($deleteOld);
    }

    public function regenerate(bool $destroy = false): void
    {
        $this->start();
        session_regenerate_id($destroy);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
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
            throw new \RuntimeException('Invalid SameSite value: ' . $value);
        }

        return $normalized;
    }
}
