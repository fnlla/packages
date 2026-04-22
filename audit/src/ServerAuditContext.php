<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

final class ServerAuditContext implements AuditContextInterface
{
    public function userId(): int|string|null
    {
        return null;
    }

    public function ipAddress(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    public function userAgent(): ?string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($agent) && $agent !== '' ? $agent : null;
    }
}




