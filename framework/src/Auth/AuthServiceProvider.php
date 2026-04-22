<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Auth;

use Fnlla\Core\Container;
use Fnlla\Core\ConfigRepository;
use Fnlla\Session\SessionInterface;
use Fnlla\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(PasswordHasher::class, function () use ($app): PasswordHasher {
            return new PasswordHasher($app->config());
        });

        $app->singleton(RememberTokenStore::class, function () use ($app): RememberTokenStore {
            $config = $app->config();
            $remember = $config->get('auth.remember', []);
            $path = is_array($remember) ? (string) ($remember['store'] ?? 'storage/auth/remember') : 'storage/auth/remember';
            $ttl = is_array($remember) ? (int) ($remember['lifetime'] ?? 1209600) : 1209600;
            $path = $this->resolvePath($app, $path);
            return new RememberTokenStore($path, $ttl);
        });

        $app->singleton(PasswordResetStore::class, function () use ($app): PasswordResetStore {
            $config = $app->config();
            $reset = $config->get('auth.reset', []);
            $path = is_array($reset) ? (string) ($reset['store'] ?? 'storage/auth/resets') : 'storage/auth/resets';
            $ttl = is_array($reset) ? (int) ($reset['ttl'] ?? 3600) : 3600;
            $path = $this->resolvePath($app, $path);
            return new PasswordResetStore($path, $ttl);
        });

        $app->singleton(AuthManager::class, function () use ($app): AuthManager {
            $session = $app->make(SessionInterface::class);
            $hasher = $app->make(PasswordHasher::class);
            $remember = $app->config()->get('auth.remember', []);
            $rememberEnabled = is_array($remember) ? (bool) ($remember['enabled'] ?? false) : false;
            $rememberStore = $rememberEnabled ? $app->make(RememberTokenStore::class) : null;
            $cookie = $rememberEnabled ? new RememberCookie($app->config()) : null;
            return new AuthManager($app->config(), $session, $hasher, $rememberStore, $cookie);
        });

        $app->singleton(PasswordResetManager::class, function () use ($app): PasswordResetManager {
            $store = $app->make(PasswordResetStore::class);
            $hasher = $app->make(PasswordHasher::class);
            $userProvider = $this->resolveUserProvider($app);
            return new PasswordResetManager($store, $hasher, $userProvider);
        });
    }

    private function resolveUserProvider(Container $app): UserProviderInterface
    {
        $config = $app->config();
        $provider = $config->get('auth.provider', []);
        if ($provider instanceof UserProviderInterface) {
            return $provider;
        }
        if (is_callable($provider)) {
            return new CallableUserProvider($provider, null);
        }
        if (is_array($provider)) {
            $byId = $provider['by_id'] ?? null;
            $byToken = $provider['by_token'] ?? null;
            $byCredentials = $provider['by_credentials'] ?? null;
            $validate = $provider['validate'] ?? null;
            $create = $provider['create'] ?? null;
            $updatePassword = $provider['update_password'] ?? null;
            return new CallableUserProvider($byId, $byToken, $byCredentials, $validate, $create, $updatePassword);
        }
        return new CallableUserProvider(null, null);
    }

    private function resolvePath(Container $app, string $path): string
    {
        if ($path === '') {
            $path = 'storage/auth';
        }
        $isAbsolute = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
        if ($isAbsolute) {
            return $path;
        }
        $root = method_exists($app, 'basePath') ? $app->basePath() : ConfigRepository::resolveAppRoot();
        return rtrim((string) $root, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}
