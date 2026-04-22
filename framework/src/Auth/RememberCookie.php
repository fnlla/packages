<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Core\ConfigRepository;
use Fnlla\Support\Env;

final class RememberCookie
{
    private string $name;
    private int $lifetime;
    private string $path;
    private string $domain;
    private bool $secure;
    private bool $httpOnly;
    private string $sameSite;

    public function __construct(ConfigRepository $config)
    {
        $this->name = (string) $config->get('auth.remember.cookie', 'remember_token');
        $this->lifetime = (int) $config->get('auth.remember.lifetime', 1209600);
        $this->path = (string) $config->get('auth.remember.path', '/');
        $this->domain = (string) $config->get('auth.remember.domain', '');
        $secure = $config->get('auth.remember.secure', null);
        $this->secure = is_bool($secure) ? $secure : $this->defaultSecure();
        $this->httpOnly = (bool) $config->get('auth.remember.httponly', true);
        $this->sameSite = $this->normalizeSameSite((string) $config->get('auth.remember.samesite', 'Lax'));
        if ($this->sameSite === 'None') {
            $this->secure = true;
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function header(string $token): string
    {
        return $this->buildHeader($token, $this->lifetime);
    }

    public function forgetHeader(): string
    {
        return $this->buildHeader('', 0);
    }

    private function buildHeader(string $value, int $lifetime): string
    {
        $parts = [rawurlencode($this->name) . '=' . rawurlencode($value)];
        if ($lifetime > 0) {
            $parts[] = 'Max-Age=' . $lifetime;
        } else {
            $parts[] = 'Max-Age=0';
        }
        if ($this->path !== '') {
            $parts[] = 'Path=' . $this->path;
        }
        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }
        if ($this->secure) {
            $parts[] = 'Secure';
        }
        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($this->sameSite !== '') {
            $parts[] = 'SameSite=' . $this->sameSite;
        }
        return implode('; ', $parts);
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
            return 'Lax';
        }
        return $normalized;
    }
}
