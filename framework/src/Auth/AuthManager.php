<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Core\ConfigRepository;
use Fnlla\Http\Request;
use Fnlla\Session\SessionInterface;
use RuntimeException;

final class AuthManager
{
    /**
     * @var array<string, mixed>
     */
    private array $guards = [];
    private ?UserProviderInterface $provider = null;
    private string $defaultGuard = 'session';
    private bool $rememberEnabled = false;

    public function __construct(
        private ConfigRepository $config,
        private SessionInterface $session,
        private PasswordHasher $hasher,
        private ?RememberTokenStore $rememberStore = null,
        private ?RememberCookie $rememberCookie = null
    ) {
        $auth = $config->get('auth', []);
        if (is_array($auth)) {
            $this->defaultGuard = (string) ($auth['guard'] ?? 'session');
            $remember = $auth['remember'] ?? [];
            if (is_array($remember)) {
                $this->rememberEnabled = (bool) ($remember['enabled'] ?? false);
            }
        }
    }

    public function guard(?string $name = null): mixed
    {
        $name = $name ?? $this->defaultGuard;
        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        $provider = $this->resolveProvider();
        if ($name === 'token') {
            $guard = new TokenGuard($provider);
            return $this->guards[$name] = $guard;
        }

        $sessionKey = (string) ($this->config->get('auth.session_key', '_auth_user'));
        $guard = new SessionGuard($this->session, $provider, $sessionKey);
        return $this->guards[$name] = $guard;
    }

    public function user(?Request $request = null): mixed
    {
        $guard = $this->guard();
        if ($guard instanceof TokenGuard && $request !== null) {
            return $guard->user($request);
        }
        if ($guard instanceof SessionGuard) {
            $user = $guard->user();
            if ($user !== null || $request === null) {
                return $user;
            }
            return $this->rememberUser($request, $guard);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(array $credentials, bool $remember = false, ?Request $request = null): AuthResult
    {
        $provider = $this->resolveProvider();
        if (!$provider instanceof CredentialsUserProviderInterface) {
            throw new RuntimeException('Auth provider does not support credential validation.');
        }

        $user = $provider->retrieveByCredentials($credentials);
        if ($user === null || !$provider->validateCredentials($user, $credentials)) {
            return new AuthResult(false, null, null);
        }

        $token = $this->login($user, $remember);
        return new AuthResult(true, $user, $token);
    }

    public function login(mixed $userOrId, bool $remember = false): ?string
    {
        $guard = $this->guard();
        if ($guard instanceof SessionGuard) {
            $guard->login($userOrId);
        }

        if ($remember && $this->rememberEnabled && $this->rememberStore instanceof RememberTokenStore) {
            $id = $this->extractId($userOrId);
            if ($id !== null) {
                return $this->rememberStore->issue($id);
            }
        }

        return null;
    }

    public function logout(?string $rememberToken = null): void
    {
        $guard = $this->guard();
        if ($guard instanceof SessionGuard) {
            $guard->logout();
        }

        if ($rememberToken !== null && $this->rememberStore instanceof RememberTokenStore) {
            $this->rememberStore->forget($rememberToken);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function register(array $data): mixed
    {
        $provider = $this->resolveProvider();
        if (!$provider instanceof RegistrationUserProviderInterface) {
            throw new RuntimeException('Auth provider does not support registration.');
        }

        if (isset($data['password']) && is_string($data['password'])) {
            $data['password'] = $this->hasher->hash($data['password']);
        }

        return $provider->createUser($data);
    }

    public function check(?Request $request = null): bool
    {
        return $this->user($request) !== null;
    }

    public function id(?Request $request = null): string|int|null
    {
        $user = $this->user($request);
        if ($user === null) {
            return null;
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
        if (is_object($user) && property_exists($user, 'user_id')) {
            return $user->user_id;
        }

        return null;
    }

    public function rememberCookie(): ?RememberCookie
    {
        if (!$this->rememberEnabled) {
            return null;
        }
        if ($this->rememberCookie instanceof RememberCookie) {
            return $this->rememberCookie;
        }
        $this->rememberCookie = new RememberCookie($this->config);
        return $this->rememberCookie;
    }

    private function resolveProvider(): UserProviderInterface
    {
        if ($this->provider instanceof UserProviderInterface) {
            return $this->provider;
        }

        $provider = $this->config->get('auth.provider', []);
        if ($provider instanceof UserProviderInterface) {
            $this->provider = $provider;
            return $this->provider;
        }

        if (is_callable($provider)) {
            $this->provider = new CallableUserProvider($provider, null);
            return $this->provider;
        }

        if (is_array($provider)) {
            $byId = $provider['by_id'] ?? null;
            $byToken = $provider['by_token'] ?? null;
            $byCredentials = $provider['by_credentials'] ?? null;
            $validate = $provider['validate'] ?? null;
            $create = $provider['create'] ?? null;
            $updatePassword = $provider['update_password'] ?? null;
            $this->provider = new CallableUserProvider($byId, $byToken, $byCredentials, $validate, $create, $updatePassword);
            return $this->provider;
        }

        throw new RuntimeException('Auth provider is not configured.');
    }

    private function rememberUser(Request $request, SessionGuard $guard): mixed
    {
        if (!$this->rememberEnabled || !$this->rememberStore instanceof RememberTokenStore) {
            return null;
        }

        $cookie = $this->rememberCookie();
        if (!$cookie instanceof RememberCookie) {
            return null;
        }

        $token = $request->getCookieParams()[$cookie->name()] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        $userId = $this->rememberStore->validate($token);
        if ($userId === null) {
            return null;
        }

        $user = $this->resolveProvider()->retrieveById($userId);
        if ($user === null) {
            $this->rememberStore->forget($token);
            return null;
        }

        $guard->login($userId);
        return $user;
    }

    private function extractId(mixed $userOrId): string|int|null
    {
        if (is_int($userOrId) || is_string($userOrId)) {
            return $userOrId;
        }
        if (is_object($userOrId) && method_exists($userOrId, 'getAuthIdentifier')) {
            return $userOrId->getAuthIdentifier();
        }
        if (is_array($userOrId) && isset($userOrId['id'])) {
            return $userOrId['id'];
        }
        if (is_object($userOrId) && property_exists($userOrId, 'id')) {
            return $userOrId->id;
        }
        return null;
    }
}
