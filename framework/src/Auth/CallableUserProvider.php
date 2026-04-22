<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

final class CallableUserProvider implements UserProviderInterface, CredentialsUserProviderInterface, RegistrationUserProviderInterface, PasswordResetUserProviderInterface
{
    /** @var callable|null */
    private $byId;
    /** @var callable|null */
    private $byToken;
    /** @var callable|null */
    private $byCredentials;
    /** @var callable|null */
    private $validate;
    /** @var callable|null */
    private $create;
    /** @var callable|null */
    private $updatePassword;

    public function __construct(
        ?callable $byId = null,
        ?callable $byToken = null,
        ?callable $byCredentials = null,
        ?callable $validate = null,
        ?callable $create = null,
        ?callable $updatePassword = null
    ) {
        $this->byId = $byId;
        $this->byToken = $byToken;
        $this->byCredentials = $byCredentials;
        $this->validate = $validate;
        $this->create = $create;
        $this->updatePassword = $updatePassword;
    }

    public function retrieveById(string|int $id): mixed
    {
        if (is_callable($this->byId)) {
            return ($this->byId)($id);
        }
        return null;
    }

    public function retrieveByToken(string $token): mixed
    {
        if (is_callable($this->byToken)) {
            return ($this->byToken)($token);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(array $credentials): mixed
    {
        if (!is_callable($this->byCredentials)) {
            return null;
        }
        return ($this->byCredentials)($credentials);
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(mixed $user, array $credentials): bool
    {
        if (is_callable($this->validate)) {
            return (bool) ($this->validate)($user, $credentials);
        }

        if (is_object($user) && method_exists($user, 'getAuthPassword')) {
            $hash = $user->getAuthPassword();
            return is_string($hash) && isset($credentials['password']) && password_verify((string) $credentials['password'], $hash);
        }

        if (is_array($user) && isset($user['password']) && isset($credentials['password'])) {
            return password_verify((string) $credentials['password'], (string) $user['password']);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createUser(array $data): mixed
    {
        if (!is_callable($this->create)) {
            return null;
        }
        return ($this->create)($data);
    }

    public function updatePassword(mixed $user, string $passwordHash): void
    {
        if (!is_callable($this->updatePassword)) {
            return;
        }
        ($this->updatePassword)($user, $passwordHash);
    }
}
