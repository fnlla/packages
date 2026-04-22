<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

use RuntimeException;

final class ImapMailboxClient implements MailboxClientInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config)
    {
    }

    /**
     * @return array<int, string>
     */
    public function listFolders(): array
    {
        $stream = $this->openMailbox($this->defaultFolder());
        $base = $this->baseMailbox();

        $folders = @imap_list($stream, $base, '*');
        imap_close($stream);

        if (!is_array($folders)) {
            return [];
        }

        return array_values(array_map(function (string $item): string {
            return preg_replace('/^\{.*\}/', '', $item) ?? $item;
        }, $folders));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(string $folder, int $limit = 50, int $offset = 0): array
    {
        $stream = $this->openMailbox($folder);
        $uids = imap_search($stream, 'ALL', SE_UID) ?: [];
        rsort($uids);

        $limit = $limit > 0 ? $limit : 50;
        $offset = $offset >= 0 ? $offset : 0;
        $slice = array_slice($uids, $offset, $limit);

        $items = [];
        foreach ($slice as $uid) {
            $overview = imap_fetch_overview($stream, (string) $uid, FT_UID) ?: [];
            $row = $overview[0] ?? null;
            if ($row === null) {
                continue;
            }
            $items[] = [
                'uid' => (int) $uid,
                'subject' => $row->subject ?? '',
                'from' => $row->from ?? '',
                'to' => $row->to ?? '',
                'date' => $row->date ?? '',
                'seen' => isset($row->seen) ? (bool) $row->seen : false,
            ];
        }

        imap_close($stream);
        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessage(string $folder, int $uid): array
    {
        $stream = $this->openMailbox($folder);
        $overview = imap_fetch_overview($stream, (string) $uid, FT_UID) ?: [];
        $row = $overview[0] ?? null;
        $body = imap_body($stream, $uid, FT_UID) ?: '';
        imap_close($stream);

        return [
            'uid' => $uid,
            'subject' => $row->subject ?? '',
            'from' => $row->from ?? '',
            'to' => $row->to ?? '',
            'date' => $row->date ?? '',
            'body' => $body,
        ];
    }

    public function deleteMessage(string $folder, int $uid): bool
    {
        $stream = $this->openMailbox($folder);
        $result = (bool) imap_delete($stream, (string) $uid, FT_UID);
        imap_expunge($stream);
        imap_close($stream);
        return (bool) $result;
    }

    private function openMailbox(string $folder): object
    {
        if (!function_exists('imap_open')) {
            throw new RuntimeException('ext-imap is required for IMAP access.');
        }

        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 993);
        $flags = (string) ($this->config['flags'] ?? '/imap/ssl');
        $username = (string) ($this->config['username'] ?? '');
        $password = (string) ($this->config['password'] ?? '');

        if ($host === '' || $username === '') {
            throw new RuntimeException('IMAP credentials are not configured.');
        }

        $mailbox = '{' . $host . ':' . $port . $flags . '}' . $folder;
        $stream = @imap_open($mailbox, $username, $password, 0, 1);

        if (!$stream) {
            $errors = imap_errors();
            $error = is_array($errors) && $errors !== [] ? $errors[0] : 'Unable to open IMAP mailbox.';
            throw new RuntimeException((string) $error);
        }

        return $stream;
    }

    private function baseMailbox(): string
    {
        $host = (string) ($this->config['host'] ?? '');
        $port = (int) ($this->config['port'] ?? 993);
        $flags = (string) ($this->config['flags'] ?? '/imap/ssl');
        return '{' . $host . ':' . $port . $flags . '}';
    }

    private function defaultFolder(): string
    {
        $folder = (string) ($this->config['folder'] ?? 'INBOX');
        return $folder !== '' ? $folder : 'INBOX';
    }
}

