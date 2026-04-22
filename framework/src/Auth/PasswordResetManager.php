<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use RuntimeException;

final class PasswordResetManager
{
    public function __construct(
        private PasswordResetStore $store,
        private PasswordHasher $hasher,
        private UserProviderInterface $provider
    ) {
    }

    public function createToken(mixed $user): string
    {
        $id = $this->extractId($user);
        if ($id === null) {
            throw new RuntimeException('Unable to resolve user id for reset token.');
        }
        return $this->store->issue($id);
    }

    public function validateToken(mixed $user, string $token): bool
    {
        $id = $this->extractId($user);
        if ($id === null) {
            return false;
        }
        return $this->store->validate($id, $token);
    }

    public function reset(mixed $user, string $token, string $newPassword): bool
    {
        $id = $this->extractId($user);
        if ($id === null) {
            return false;
        }
        if (!$this->store->consume($id, $token)) {
            return false;
        }
        if (!$this->provider instanceof PasswordResetUserProviderInterface) {
            throw new RuntimeException('User provider does not support password resets.');
        }
        $hash = $this->hasher->hash($newPassword);
        $this->provider->updatePassword($user, $hash);
        return true;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function findUser(array $credentials): mixed
    {
        if ($this->provider instanceof CredentialsUserProviderInterface) {
            return $this->provider->retrieveByCredentials($credentials);
        }
        return null;
    }

    private function extractId(mixed $user): string|int|null
    {
        if (is_int($user) || is_string($user)) {
            return $user;
        }
        if (is_object($user) && method_exists($user, 'getAuthIdentifier')) {
            return $user->getAuthIdentifier();
        }
        if (is_array($user) && isset($user['id'])) {
            return $user['id'];
        }
        if (is_object($user) && property_exists($user, 'id')) {
            return $user->id;
        }
        return null;
    }
}
