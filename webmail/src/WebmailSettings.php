<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use Fnlla\Core\ConfigRepository;
use Fnlla\Settings\SettingsStore;

final class WebmailSettings
{
    public function __construct(
        private ConfigRepository $config,
        private ?SettingsStore $store = null,
        private ?WebmailCipher $cipher = null,
        private ?WebmailSettingsKeys $keys = null
    )
    {
    }

    /**
     * @return array{host:string,port:int,flags:string,username:string,password:string,folder:string}
     */
    public function imap(): array
    {
        $imap = $this->configArray('webmail.imap');
        $imap = $this->normalizeImap($imap);

        $host = $this->storeValue('webmail.imap.host');
        if ($host !== null) {
            $imap['host'] = $host;
        }

        $port = $this->storeInt('webmail.imap.port');
        if ($port !== null) {
            $imap['port'] = $port;
        }

        $flags = $this->storeValue('webmail.imap.flags');
        if ($flags !== null) {
            $imap['flags'] = $flags;
        }

        $username = $this->storeValue('webmail.imap.username');
        if ($username !== null) {
            $imap['username'] = $username;
        }

        $password = $this->storeSecret('webmail.imap.password');
        if ($password !== null) {
            $imap['password'] = $password;
        }

        $folder = $this->storeValue('webmail.imap.folder');
        if ($folder !== null) {
            $imap['folder'] = $folder;
        }

        return $imap;
    }

    /**
     * @return array{dsn:string,host:string,port:int,username:string,password:string,encryption:string,from_address:string,from_name:string}
     */
    public function smtp(): array
    {
        $webmail = $this->configArray('webmail.smtp');
        $mail = $this->configArray('mail');
        $mailFrom = isset($mail['from']) && is_array($mail['from']) ? $mail['from'] : [];

        $smtp = [
            'dsn' => (string) ($webmail['dsn'] ?? $mail['dsn'] ?? ''),
            'host' => (string) ($webmail['host'] ?? $mail['host'] ?? ''),
            'port' => (int) ($webmail['port'] ?? $mail['port'] ?? 0),
            'username' => (string) ($webmail['username'] ?? $mail['username'] ?? ''),
            'password' => (string) ($webmail['password'] ?? $mail['password'] ?? ''),
            'encryption' => (string) ($webmail['encryption'] ?? $mail['encryption'] ?? ''),
            'from_address' => (string) ($webmail['from_address'] ?? $mailFrom['address'] ?? $mail['from_address'] ?? ''),
            'from_name' => (string) ($webmail['from_name'] ?? $mailFrom['name'] ?? $mail['from_name'] ?? ''),
        ];

        $dsn = $this->storeValue('webmail.smtp.dsn');
        if ($dsn !== null) {
            $smtp['dsn'] = $dsn;
        }

        $host = $this->storeValue('webmail.smtp.host');
        if ($host !== null) {
            $smtp['host'] = $host;
        }

        $port = $this->storeInt('webmail.smtp.port');
        if ($port !== null) {
            $smtp['port'] = $port;
        }

        $username = $this->storeValue('webmail.smtp.username');
        if ($username !== null) {
            $smtp['username'] = $username;
        }

        $password = $this->storeSecret('webmail.smtp.password');
        if ($password !== null) {
            $smtp['password'] = $password;
        }

        $encryption = $this->storeValue('webmail.smtp.encryption');
        if ($encryption !== null) {
            $smtp['encryption'] = $encryption;
        }

        $fromAddress = $this->storeValue('webmail.smtp.from_address');
        if ($fromAddress !== null) {
            $smtp['from_address'] = $fromAddress;
        }

        $fromName = $this->storeValue('webmail.smtp.from_name');
        if ($fromName !== null) {
            $smtp['from_name'] = $fromName;
        }

        return $smtp;
    }

    public function imapPasswordSet(): bool
    {
        $imap = $this->imap();
        return (string) ($imap['password'] ?? '') !== '';
    }

    public function smtpPasswordSet(): bool
    {
        $smtp = $this->smtp();
        return (string) ($smtp['password'] ?? '') !== '';
    }

    /**
     * @return array<string, mixed>
     */
    private function configArray(string $key): array
    {
        $value = $this->config->get($key, []);
        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $imap
     * @return array{host:string,port:int,flags:string,username:string,password:string,folder:string}
     */
    private function normalizeImap(array $imap): array
    {
        return [
            'host' => (string) ($imap['host'] ?? ''),
            'port' => (int) ($imap['port'] ?? 993),
            'flags' => (string) ($imap['flags'] ?? '/imap/ssl'),
            'username' => (string) ($imap['username'] ?? ''),
            'password' => (string) ($imap['password'] ?? ''),
            'folder' => (string) ($imap['folder'] ?? 'INBOX'),
        ];
    }

    private function storeValue(string $key): ?string
    {
        if ($this->store === null || !$this->storeReady()) {
            return null;
        }

        if ($this->keys instanceof WebmailSettingsKeys) {
            $key = $this->keys->resolve($key);
        }

        $value = $this->store->get($key, '');
        return $value !== '' ? $value : null;
    }

    private function storeReady(): bool
    {
        if ($this->store === null) {
            return false;
        }

        if (method_exists($this->store, 'ready')) {
            return (bool) $this->store->ready();
        }

        return true;
    }

    private function storeSecret(string $key): ?string
    {
        $value = $this->storeValue($key);
        if ($value === null) {
            return null;
        }

        if ($this->cipher === null) {
            return $value;
        }

        $decrypted = $this->cipher->decrypt($value);
        return $decrypted !== '' ? $decrypted : null;
    }

    private function storeInt(string $key): ?int
    {
        $value = $this->storeValue($key);
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }
}
