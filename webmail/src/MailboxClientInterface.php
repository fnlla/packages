<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Webmail;

interface MailboxClientInterface
{
    /**
     * @return array<int, string>
     */
    public function listFolders(): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(string $folder, int $limit = 50, int $offset = 0): array;

    /**
     * @return array<string, mixed>
     */
    public function getMessage(string $folder, int $uid): array;

    public function deleteMessage(string $folder, int $uid): bool;
}

