<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

final class AuthAuditContext implements AuditContextInterface
{
    public function __construct(private object $auth, private AuditContextInterface $fallback)
    {
    }

    public function userId(): int|string|null
    {
        $user = null;
        if (method_exists($this->auth, 'user')) {
            $user = $this->auth->user();
        }

        if (is_int($user) || is_string($user)) {
            return $user;
        }

        if (is_object($user)) {
            if (method_exists($user, 'getAuthIdentifier')) {
                return $user->getAuthIdentifier();
            }
            if (property_exists($user, 'id')) {
                return $user->id;
            }
        }

        if (is_array($user) && isset($user['id'])) {
            return $user['id'];
        }

        return null;
    }

    public function ipAddress(): ?string
    {
        return $this->fallback->ipAddress();
    }

    public function userAgent(): ?string
    {
        return $this->fallback->userAgent();
    }
}




