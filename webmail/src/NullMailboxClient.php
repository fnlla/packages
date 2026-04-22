<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

final class NullMailboxClient implements MailboxClientInterface
{
    public function listFolders(): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(string $folder, int $limit = 50, int $offset = 0): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessage(string $folder, int $uid): array
    {
        return [];
    }

    public function deleteMessage(string $folder, int $uid): bool
    {
        return false;
    }
}

