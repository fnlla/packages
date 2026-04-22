<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Session\SessionInterface;
use RuntimeException;

final class SessionGuard
{
    public function __construct(
        private SessionInterface $session,
        private UserProviderInterface $provider,
        private string $sessionKey = '_auth_user'
    ) {
    }

    public function user(): mixed
    {
        $id = $this->id();
        if ($id === null) {
            return null;
        }
        return $this->provider->retrieveById($id);
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function id(): string|int|null
    {
        return $this->session->get($this->sessionKey);
    }

    public function login(mixed $userOrId): void
    {
        $id = $this->extractId($userOrId);
        if ($id === null) {
            throw new RuntimeException('Unable to resolve user id.');
        }
        $this->session->regenerateId(true);
        $this->session->put($this->sessionKey, $id);
    }

    public function logout(): void
    {
        $this->session->forget($this->sessionKey);
        $this->session->regenerateId(true);
    }

    private function extractId(mixed $userOrId): string|int|null
    {
        if (is_int($userOrId) || is_string($userOrId)) {
            return $userOrId;
        }
        if (is_array($userOrId) && isset($userOrId['id'])) {
            return $userOrId['id'];
        }
        if (is_object($userOrId)) {
            if (method_exists($userOrId, 'getAuthIdentifier')) {
                return $userOrId->getAuthIdentifier();
            }
            if (property_exists($userOrId, 'id')) {
                return $userOrId->id;
            }
        }
        return null;
    }
}
