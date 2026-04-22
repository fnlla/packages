<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail\Http;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Settings\SettingsStore;
use Fnlla\Webmail\WebmailCipher;
use Fnlla\Webmail\WebmailSettings;
use Fnlla\Webmail\WebmailSettingsKeys;

final class WebmailSettingsController
{
    public function __construct(
        private SettingsStore $store,
        private WebmailSettings $settings,
        private WebmailCipher $cipher,
        private WebmailSettingsKeys $keys,
        private ConfigRepository $config,
        private Container $app
    ) {
    }

    public function show(Request $request): Response
    {
        $tenantError = $this->tenantScopeError();
        if ($tenantError !== null) {
            return Response::json(['error' => $tenantError], 503);
        }

        try {
            $imap = $this->settings->imap();
            $smtp = $this->settings->smtp();
        } catch (\Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 503);
        }

        $imap['password_set'] = $this->settings->imapPasswordSet();
        $smtp['password_set'] = $this->settings->smtpPasswordSet();

        unset($imap['password'], $smtp['password']);

        $storeReady = $this->storeReady();

        $payload = [
            'imap' => $imap,
            'smtp' => $smtp,
            'store_ready' => $storeReady,
        ];

        if (!$storeReady) {
            $payload['store_error'] = 'Settings store is not available. Check the database and fnlla/settings migrations.';
        }

        return Response::json($payload);
    }

    public function update(Request $request): Response
    {
        $tenantError = $this->tenantScopeError();
        if ($tenantError !== null) {
            return Response::json(['error' => $tenantError], 503);
        }

        if (!$this->storeReady()) {
            return Response::json(['error' => 'Settings store is not available. Check the database and fnlla/settings migrations.'], 503);
        }

        $body = $request->getParsedBody();
        $payload = is_array($body) ? $body : [];

        $imap = isset($payload['imap']) && is_array($payload['imap']) ? $payload['imap'] : [];
        $smtp = isset($payload['smtp']) && is_array($payload['smtp']) ? $payload['smtp'] : [];

        $errors = $this->validatePayload($imap, $smtp);
        if ($errors !== []) {
            return Response::json(['errors' => $errors], 422);
        }

        if ($this->requiresEncryption() && !$this->cipher->canEncrypt() && $this->payloadHasSecrets($imap, $smtp)) {
            return Response::json(['error' => 'Encryption key is not configured.'], 500);
        }

        $updates = [];
        $changedKeys = [];

        $this->collectString($updates, $changedKeys, 'webmail.imap.host', $imap, 'host');
        $this->collectInt($updates, $changedKeys, 'webmail.imap.port', $imap, 'port');
        $this->collectString($updates, $changedKeys, 'webmail.imap.flags', $imap, 'flags');
        $this->collectString($updates, $changedKeys, 'webmail.imap.username', $imap, 'username');
        $this->collectSecret($updates, $changedKeys, 'webmail.imap.password', $imap, 'password');
        $this->collectString($updates, $changedKeys, 'webmail.imap.folder', $imap, 'folder');

        $this->collectRaw($updates, $changedKeys, 'webmail.smtp.dsn', $smtp, 'dsn');
        $this->collectString($updates, $changedKeys, 'webmail.smtp.host', $smtp, 'host');
        $this->collectInt($updates, $changedKeys, 'webmail.smtp.port', $smtp, 'port');
        $this->collectString($updates, $changedKeys, 'webmail.smtp.username', $smtp, 'username');
        $this->collectSecret($updates, $changedKeys, 'webmail.smtp.password', $smtp, 'password');
        $this->collectString($updates, $changedKeys, 'webmail.smtp.encryption', $smtp, 'encryption');
        $this->collectString($updates, $changedKeys, 'webmail.smtp.from_address', $smtp, 'from_address');
        $this->collectString($updates, $changedKeys, 'webmail.smtp.from_name', $smtp, 'from_name');

        if ($updates !== []) {
            $this->store->setMany($updates);
            $this->auditUpdate($changedKeys);
        }

        return Response::json([
            'updated' => $changedKeys,
        ]);
    }

    /**
     * @param array<string, string|null> $updates
     * @param array<int, string> $changedKeys
     * @param array<string, mixed> $payload
     */
    private function collectString(array &$updates, array &$changedKeys, string $key, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];
        if ($value === null) {
            $updates[$this->keys->resolve($key)] = '';
            $changedKeys[] = $key;
            return;
        }

        if (is_scalar($value)) {
            $updates[$this->keys->resolve($key)] = trim((string) $value);
            $changedKeys[] = $key;
        }
    }

    /**
     * @param array<string, string|null> $updates
     * @param array<int, string> $changedKeys
     * @param array<string, mixed> $payload
     */
    private function collectRaw(array &$updates, array &$changedKeys, string $key, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];
        if ($value === null) {
            $updates[$this->keys->resolve($key)] = '';
            $changedKeys[] = $key;
            return;
        }

        if (is_scalar($value)) {
            $updates[$this->keys->resolve($key)] = (string) $value;
            $changedKeys[] = $key;
        }
    }

    /**
     * @param array<string, string|null> $updates
     * @param array<int, string> $changedKeys
     * @param array<string, mixed> $payload
     */
    private function collectSecret(array &$updates, array &$changedKeys, string $key, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];
        if ($value === null) {
            $updates[$this->keys->resolve($key)] = '';
            $changedKeys[] = $key;
            return;
        }

        if (is_scalar($value)) {
            $raw = (string) $value;
            if ($raw === '') {
                $updates[$this->keys->resolve($key)] = '';
                $changedKeys[] = $key;
                return;
            }

            $updates[$this->keys->resolve($key)] = $this->cipher->encrypt($raw);
            $changedKeys[] = $key;
        }
    }

    /**
     * @param array<string, string|null> $updates
     * @param array<int, string> $changedKeys
     * @param array<string, mixed> $payload
     */
    private function collectInt(array &$updates, array &$changedKeys, string $key, array $payload, string $field): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];
        if ($value === null || $value === '') {
            $updates[$this->keys->resolve($key)] = '';
            $changedKeys[] = $key;
            return;
        }

        if (is_numeric($value)) {
            $updates[$this->keys->resolve($key)] = (string) (int) $value;
            $changedKeys[] = $key;
        }
    }

    /**
     * @param array<string, mixed> $imap
     * @param array<string, mixed> $smtp
     * @return array<string, string>
     */
    private function validatePayload(array $imap, array $smtp): array
    {
        $errors = [];

        if (array_key_exists('host', $imap)) {
            $host = trim((string) ($imap['host'] ?? ''));
            if ($host !== '') {
                if (!$this->isValidHost($host)) {
                    $errors['imap.host'] = 'Invalid IMAP host.';
                } elseif (!$this->hostAllowed($host, $this->imapAllowlist())) {
                    $errors['imap.host'] = 'IMAP host is not allowed.';
                }
            }
        }

        if (array_key_exists('port', $imap)) {
            if (!$this->isValidPort($imap['port'] ?? null)) {
                $errors['imap.port'] = 'Invalid IMAP port.';
            }
        }

        if (array_key_exists('host', $smtp)) {
            $host = trim((string) ($smtp['host'] ?? ''));
            if ($host !== '') {
                if (!$this->isValidHost($host)) {
                    $errors['smtp.host'] = 'Invalid SMTP host.';
                } elseif (!$this->hostAllowed($host, $this->smtpAllowlist())) {
                    $errors['smtp.host'] = 'SMTP host is not allowed.';
                }
            }
        }

        if (array_key_exists('port', $smtp)) {
            if (!$this->isValidPort($smtp['port'] ?? null)) {
                $errors['smtp.port'] = 'Invalid SMTP port.';
            }
        }

        if (array_key_exists('from_address', $smtp)) {
            $from = trim((string) ($smtp['from_address'] ?? ''));
            if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $errors['smtp.from_address'] = 'Invalid from email address.';
            }
        }

        if (array_key_exists('encryption', $smtp)) {
            $enc = strtolower(trim((string) ($smtp['encryption'] ?? '')));
            if ($enc !== '' && !in_array($enc, ['ssl', 'tls', 'starttls', 'smtps', 'none'], true)) {
                $errors['smtp.encryption'] = 'Invalid encryption value.';
            }
        }

        return $errors;
    }

    private function isValidPort(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        if (!is_numeric($value)) {
            return false;
        }
        $port = (int) $value;
        return $port >= 1 && $port <= 65535;
    }

    private function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return true;
        }

        return preg_match('/^[A-Za-z0-9.-]+$/', $host) === 1;
    }

    /**
     * @param array<int, string> $allowlist
     */
    private function hostAllowed(string $host, array $allowlist): bool
    {
        if ($allowlist === []) {
            return true;
        }

        $host = strtolower($host);
        foreach ($allowlist as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if ($pattern === $host) {
                return true;
            }
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (preg_match($regex, $host) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function imapAllowlist(): array
    {
        $security = $this->config->get('webmail.security', []);
        return $this->normalizeAllowlist($security['imap_host_allowlist'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    private function smtpAllowlist(): array
    {
        $security = $this->config->get('webmail.security', []);
        return $this->normalizeAllowlist($security['smtp_host_allowlist'] ?? []);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAllowlist(mixed $value): array
    {
        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            return array_values(array_filter($parts, static fn ($item) => $item !== ''));
        }

        if (is_array($value)) {
            $list = [];
            foreach ($value as $item) {
                if (!is_string($item)) {
                    continue;
                }
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $list[] = $item;
            }
            return $list;
        }

        return [];
    }

    private function requiresEncryption(): bool
    {
        $security = $this->config->get('webmail.security', []);
        return is_array($security) ? (bool) ($security['require_encryption'] ?? false) : false;
    }

    private function storeReady(): bool
    {
        if (method_exists($this->store, 'ready')) {
            return (bool) $this->store->ready();
        }

        return true;
    }

    private function tenantScopeError(): ?string
    {
        if (method_exists($this->keys, 'tenantScopeError')) {
            return $this->keys->tenantScopeError();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $imap
     * @param array<string, mixed> $smtp
     */
    private function payloadHasSecrets(array $imap, array $smtp): bool
    {
        $imapSecret = array_key_exists('password', $imap) && (string) ($imap['password'] ?? '') !== '';
        $smtpSecret = array_key_exists('password', $smtp) && (string) ($smtp['password'] ?? '') !== '';
        return $imapSecret || $smtpSecret;
    }

    /**
     * @param array<int, string> $changedKeys
     */
    private function auditUpdate(array $changedKeys): void
    {
        if (!class_exists(\Fnlla\Audit\AuditLogger::class)) {
            return;
        }

        if (!$this->app->has(\Fnlla\Audit\AuditLogger::class)) {
            return;
        }

        $logger = $this->app->make(\Fnlla\Audit\AuditLogger::class);
        if (!$logger instanceof \Fnlla\Audit\AuditLogger) {
            return;
        }

        $tenantId = null;
        if (class_exists(\Fnlla\Tenancy\TenantContext::class)) {
            $tenantId = \Fnlla\Tenancy\TenantContext::id();
        }

        $logger->record(
            'webmail.settings.update',
            'settings',
            null,
            [
                'keys' => $changedKeys,
                'tenant_id' => $tenantId,
            ]
        );
    }
}


