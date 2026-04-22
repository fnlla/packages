<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;
use Fnlla\Core\ConfigRepository;

final class AiSecurityLintCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:security-lint';
    }

    public function getDescription(): string
    {
        return 'Lint security-related configuration for risky defaults.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $config = ConfigRepository::fromRoot($root);
        $appEnv = (string) $config->get('app.env', 'local');

        $http = $config->get('http', []);
        $session = $config->get('session', []);
        $security = $config->get('security', []);

        $issues = [];
        $suggestions = [];

        if ($appEnv === 'prod' && !$this->csrfEnabled($http)) {
            $issues[] = 'CSRF protection is disabled in production.';
            $suggestions[] = 'Set CSRF_ENABLED=1 or enable CsrfMiddleware in http config.';
        }

        if ($appEnv === 'prod' && !$this->securityHeadersEnabled($http)) {
            $issues[] = 'Security headers middleware is disabled in production.';
            $suggestions[] = 'Set SECURITY_HEADERS_ENABLED=1 or enable SecurityHeadersMiddleware.';
        }

        if ($appEnv === 'prod' && !$this->rateLimitEnabled($http)) {
            $issues[] = 'Rate limiting is disabled in production.';
            $suggestions[] = 'Set RATE_LIMIT_ENABLED=1 and configure RATE_LIMIT_MAX/MINUTES.';
        }

        if ($appEnv === 'prod' && !$this->sessionSecure($session)) {
            $issues[] = 'Session cookies are not marked secure in production.';
            $suggestions[] = 'Set SESSION_SECURE=1.';
        }

        if ($appEnv === 'prod' && !$this->sessionHttpOnly($session)) {
            $issues[] = 'Session cookies are not HTTP-only in production.';
            $suggestions[] = 'Set SESSION_HTTPONLY=1.';
        }

        if ($this->hstsDisabled($security, $appEnv)) {
            $issues[] = 'HSTS header is not configured for production.';
            $suggestions[] = 'Set SECURITY_HSTS_MAX_AGE and ensure HTTPS is enforced.';
        }

        $io->line('Security Lint');
        $io->line('Env: ' . $appEnv);
        $io->line('');

        if ($issues === []) {
            $io->line('No critical security issues detected.');
            return 0;
        }

        $io->line('Issues:');
        foreach ($issues as $issue) {
            $io->line(' - ' . $issue);
        }

        if ($suggestions !== []) {
            $io->line('');
            $io->line('Suggestions:');
            foreach ($suggestions as $item) {
                $io->line(' - ' . $item);
            }
        }

        return 0;
    }

    private function csrfEnabled(mixed $http): bool
    {
        if (!is_array($http)) {
            return false;
        }
        $groups = $http['middleware_groups'] ?? [];
        if (!is_array($groups) || !isset($groups['web']) || !is_array($groups['web'])) {
            return false;
        }
        foreach ($groups['web'] as $middleware) {
            if ($middleware === 'Fnlla\\Csrf\\CsrfMiddleware') {
                return true;
            }
        }
        return false;
    }

    private function securityHeadersEnabled(mixed $http): bool
    {
        if (!is_array($http)) {
            return false;
        }
        $global = $http['global'] ?? [];
        if (!is_array($global)) {
            return false;
        }
        foreach ($global as $middleware) {
            if ($middleware === 'Fnlla\\SecurityHeaders\\SecurityHeadersMiddleware') {
                return true;
            }
        }
        return false;
    }

    private function rateLimitEnabled(mixed $http): bool
    {
        if (!is_array($http)) {
            return false;
        }
        $groups = $http['middleware_groups'] ?? [];
        if (!is_array($groups) || !isset($groups['web']) || !is_array($groups['web'])) {
            return false;
        }
        foreach ($groups['web'] as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'rate:')) {
                return true;
            }
        }
        return false;
    }

    private function sessionSecure(mixed $session): bool
    {
        if (!is_array($session)) {
            return false;
        }
        $cookie = $session['cookie'] ?? [];
        if (!is_array($cookie)) {
            return false;
        }
        return (bool) ($cookie['secure'] ?? false);
    }

    private function sessionHttpOnly(mixed $session): bool
    {
        if (!is_array($session)) {
            return false;
        }
        $cookie = $session['cookie'] ?? [];
        if (!is_array($cookie)) {
            return false;
        }
        return (bool) ($cookie['httponly'] ?? false);
    }

    private function hstsDisabled(mixed $security, string $env): bool
    {
        if ($env !== 'prod') {
            return false;
        }
        if (!is_array($security)) {
            return true;
        }
        $headers = $security['headers'] ?? [];
        if (!is_array($headers)) {
            return true;
        }
        $hsts = $headers['Strict-Transport-Security'] ?? null;
        return $hsts === null || $hsts === '';
    }
}
