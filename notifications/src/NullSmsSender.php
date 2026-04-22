<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications;

use RuntimeException;

final class NullSmsSender implements SmsSenderInterface
{
    public function send(string $to, string $message, array $meta = []): void
    {
        throw new RuntimeException('SMS sender is not configured.');
    }
}


