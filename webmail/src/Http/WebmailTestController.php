<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail\Http;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Mail\Address;
use Fnlla\Mail\Message;
use Fnlla\Webmail\ImapMailboxClient;
use Fnlla\Webmail\WebmailSettings;
use Fnlla\Webmail\WebmailSmtpClient;

final class WebmailTestController
{
    public function __construct(
        private WebmailSettings $settings,
        private WebmailSmtpClient $smtp,
        private ConfigRepository $config
    ) {
    }

    public function test(Request $request): Response
    {
        if (!$this->testEnabled()) {
            return Response::json(['error' => 'Webmail test endpoint is disabled.'], 403);
        }

        $body = $request->getParsedBody();
        $payload = is_array($body) ? $body : [];

        $testImap = $this->toBool($payload['imap'] ?? true);
        $testSmtp = $this->toBool($payload['smtp'] ?? true);

        if (!$testImap && !$testSmtp) {
            return Response::json(['error' => 'No tests selected.'], 422);
        }

        $results = [];

        if ($testImap) {
            $results['imap'] = $this->testImap();
        }

        if ($testSmtp) {
            $results['smtp'] = $this->testSmtp($payload);
        }

        return Response::json(['results' => $results]);
    }

    /**
     * @return array{ok:bool,details?:mixed,error?:string}
     */
    private function testImap(): array
    {
        try {
            $imap = $this->settings->imap();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $host = (string) ($imap['host'] ?? '');
        $user = (string) ($imap['username'] ?? '');

        if ($host === '' || $user === '') {
            return ['ok' => false, 'error' => 'IMAP is not configured.'];
        }

        if (!function_exists('imap_open')) {
            return ['ok' => false, 'error' => 'ext-imap is not enabled.'];
        }

        try {
            $client = new ImapMailboxClient($imap);
            $folders = $client->listFolders();
            return ['ok' => true, 'details' => ['folders' => count($folders)]];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,details?:mixed,error?:string}
     */
    private function testSmtp(array $payload): array
    {
        try {
            $smtp = $this->settings->smtp();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $dsn = (string) ($smtp['dsn'] ?? '');
        $host = (string) ($smtp['host'] ?? '');
        $to = $this->extractAddress($payload['to'] ?? '');

        if ($dsn === '' && $host === '') {
            return ['ok' => false, 'error' => 'SMTP is not configured.'];
        }

        if ($to === '') {
            return ['ok' => false, 'error' => 'Missing recipient for SMTP test.'];
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }

        if (!$this->recipientAllowed($to)) {
            return ['ok' => false, 'error' => 'Recipient is not allowed for SMTP tests.'];
        }

        $from = $this->extractAddress($payload['from'] ?? '');
        $fromName = (string) ($payload['from_name'] ?? $payload['fromName'] ?? '');

        $message = new Message(
            from: new Address($from),
            to: [new Address($to)],
            subject: 'Fnlla webmail SMTP test',
            text: 'Test email sent by Fnlla webmail diagnostics.'
        );

        if ($from === '' && $fromName !== '') {
            $message->from->name = $fromName;
        }

        try {
            $this->smtp->send($message);
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return (bool) $value;
    }

    private function extractAddress(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        $value = trim((string) $value);
        if (str_contains($value, ',')) {
            $parts = explode(',', $value);
            $value = trim((string) ($parts[0] ?? ''));
        }

        return $value;
    }

    private function testEnabled(): bool
    {
        $security = $this->config->get('webmail.security', []);
        if (!is_array($security)) {
            return true;
        }
        if (!array_key_exists('test_enabled', $security)) {
            return true;
        }

        return (bool) $security['test_enabled'];
    }

    private function recipientAllowed(string $email): bool
    {
        $security = $this->config->get('webmail.security', []);
        if (!is_array($security)) {
            return true;
        }
        $allowlist = $security['test_recipient_allowlist'] ?? [];
        $allowlist = $this->normalizeAllowlist($allowlist);
        if ($allowlist === []) {
            return true;
        }

        $email = strtolower($email);
        foreach ($allowlist as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if ($pattern === $email) {
                return true;
            }
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
                if (preg_match($regex, $email) === 1) {
                    return true;
                }
            }
        }

        return false;
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
}


