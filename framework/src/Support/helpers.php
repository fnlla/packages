<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Core\Container;
use Fnlla\Http\Router;
use Fnlla\Runtime\RequestContext;
use PDO;
use RuntimeException;

function asset(Container $app, string $path): string
{
    $config = $app->configRepository();
    $assetUrl = rtrim((string) $config->get('asset_url', ''), '/');
    if ($assetUrl !== '') {
        return $assetUrl . '/' . ltrim($path, '/');
    }
    $base = rtrim((string) $config->get('base_path', ''), '/');
    return $base . '/assets/' . ltrim($path, '/');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(Container $app, string $path = ''): string
{
    $config = $app->configRepository();
    $base = rtrim((string) $config->get('base_path', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

function site_url(Container $app): string
{
    $config = $app->configRepository();
    $basePath = rtrim((string) $config->get('base_path', ''), '/');
    $configured = rtrim((string) $config->get('site_url', ''), '/');

    if ($configured !== '') {
        return $configured . ($basePath === '' ? '' : $basePath);
    }

    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
}

function absolute_url(Container $app, string $path = ''): string
{
    if ($path === '') {
        return site_url($app);
    }
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    return rtrim(site_url($app), '/') . '/' . ltrim($path, '/');
}

function csrf_token(Container $app): string
{
    if (!class_exists(\Fnlla\Csrf\CsrfTokenManager::class) || !interface_exists(\Fnlla\Session\SessionInterface::class)) {
        throw new RuntimeException('CSRF support is not available. Ensure the core CSRF and Session modules are enabled.');
    }

    $session = $app->make(\Fnlla\Session\SessionInterface::class);
    if (!$session instanceof \Fnlla\Session\SessionInterface) {
        throw new RuntimeException('Session service is not available.');
    }

    $manager = new \Fnlla\Csrf\CsrfTokenManager($session);
    return $manager->token();
}

function csrf_field(Container $app): string
{
    $token = csrf_token($app);
    return '<input type="hidden" name="_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(Container $app, ?string $token): bool
{
    if (!class_exists(\Fnlla\Csrf\CsrfTokenManager::class) || !interface_exists(\Fnlla\Session\SessionInterface::class)) {
        return false;
    }

    $session = $app->make(\Fnlla\Session\SessionInterface::class);
    if (!$session instanceof \Fnlla\Session\SessionInterface) {
        return false;
    }

    $manager = new \Fnlla\Csrf\CsrfTokenManager($session);
    return $manager->validate($token);
}

function safe_mkdir(string $dir, int $mode = 0755, bool $recursive = true, string $context = ''): bool
{
    if ($dir === '') {
        error_log('Unable to create directory: empty path' . ($context !== '' ? ' (' . $context . ')' : ''));
        return false;
    }
    if (is_dir($dir)) {
        return true;
    }
    if (mkdir($dir, $mode, $recursive) || is_dir($dir)) {
        return true;
    }
    error_log('Unable to create directory: ' . $dir . ($context !== '' ? ' (' . $context . ')' : ''));
    return false;
}

function safe_copy(string $source, string $destination, string $context = ''): bool
{
    if (copy($source, $destination)) {
        return true;
    }
    error_log('Unable to copy file: ' . $source . ' -> ' . $destination . ($context !== '' ? ' (' . $context . ')' : ''));
    return false;
}

function safe_unlink(string $path, string $context = ''): bool
{
    if (!is_file($path)) {
        return true;
    }
    if (unlink($path)) {
        return true;
    }
    error_log('Unable to remove file: ' . $path . ($context !== '' ? ' (' . $context . ')' : ''));
    return false;
}

function request_id(Container $app): string
{
    if (!$app->has(RequestContext::class)) {
        return '';
    }
    $context = $app->make(RequestContext::class);
    return $context instanceof RequestContext ? $context->requestId() : '';
}

function csp_nonce(Container $app): string
{
    if (!$app->has(RequestContext::class)) {
        return '';
    }
    $context = $app->make(RequestContext::class);
    return $context instanceof RequestContext ? $context->cspNonce() : '';
}

function script_nonce_attr(Container $app): string
{
    $nonce = csp_nonce($app);
    if ($nonce === '') {
        return '';
    }
    return 'nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"';
}

function sql_insert_ignore(PDO $pdo): string
{
    return 'INSERT IGNORE';
}

if (!function_exists(__NAMESPACE__ . '\\app')) {
    function app(Container $app, ?string $abstract = null): mixed
    {
        if ($abstract === null) {
            return $app;
        }
        return $app->make($abstract);
    }
}

if (!function_exists(__NAMESPACE__ . '\\config')) {
    function config(Container $app, string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $app->configRepository()->all();
        }
        return $app->configRepository()->get($key, $default);
    }
}

if (!function_exists(__NAMESPACE__ . '\\env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }
}

if (!function_exists(__NAMESPACE__ . '\\route')) {
    function route(Container $app, string $name, array $params = []): string
    {
        $router = $app->make(Router::class);
        if (!$router instanceof Router) {
            return '';
        }
        return $router->url($name, $params);
    }
}

if (!function_exists(__NAMESPACE__ . '\\cache')) {
    function cache(Container $app, ?string $key = null, mixed $default = null): mixed
    {
        if (class_exists(\Fnlla\Support\Cache::class) && $app->has(\Fnlla\Support\Cache::class)) {
            $cache = $app->make(\Fnlla\Support\Cache::class);
            if ($cache instanceof \Fnlla\Support\Cache) {
                return $key === null ? $cache : $cache->get($key, $default);
            }
        }

        if (!class_exists(\Fnlla\Cache\CacheManager::class) || !$app->has(\Fnlla\Cache\CacheManager::class)) {
            return $key === null ? null : $default;
        }

        $cache = $app->make(\Fnlla\Cache\CacheManager::class);
        if (!$cache instanceof \Fnlla\Cache\CacheManager) {
            return $key === null ? null : $default;
        }

        if ($key === null) {
            return $cache;
        }

        return $cache->get($key, $default);
    }
}

if (!function_exists(__NAMESPACE__ . '\\event')) {
    function event(Container $app, object|string $event, array $payload = []): array
    {
        $dispatcher = $app->make(EventDispatcher::class);
        if (!$dispatcher instanceof EventDispatcher) {
            return [];
        }
        return $dispatcher->dispatch($event, $payload);
    }
}

if (!function_exists(__NAMESPACE__ . '\\queue')) {
    function queue(Container $app): mixed
    {
        if (class_exists(\Fnlla\Support\Queue::class) && $app->has(\Fnlla\Support\Queue::class)) {
            $queue = $app->make(\Fnlla\Support\Queue::class);
            if ($queue instanceof \Fnlla\Support\Queue) {
                return $queue;
            }
        }

        if (!class_exists(\Fnlla\Queue\QueueManager::class) || !$app->has(\Fnlla\Queue\QueueManager::class)) {
            return null;
        }
        $queue = $app->make(\Fnlla\Queue\QueueManager::class);
        return $queue instanceof \Fnlla\Queue\QueueManager ? $queue : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\logger')) {
    function logger(Container $app): mixed
    {
        if (!interface_exists(\Psr\Log\LoggerInterface::class) || !$app->has(\Psr\Log\LoggerInterface::class)) {
            return null;
        }
        $logger = $app->make(\Psr\Log\LoggerInterface::class);
        return $logger instanceof \Psr\Log\LoggerInterface ? $logger : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\session')) {
    function session(Container $app): mixed
    {
        if (!interface_exists(\Fnlla\Session\SessionInterface::class) || !$app->has(\Fnlla\Session\SessionInterface::class)) {
            return null;
        }
        $session = $app->make(\Fnlla\Session\SessionInterface::class);
        return $session instanceof \Fnlla\Session\SessionInterface ? $session : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\cookie')) {
    function cookie(Container $app): mixed
    {
        if (!class_exists(\Fnlla\Cookie\CookieJar::class) || !$app->has(\Fnlla\Cookie\CookieJar::class)) {
            return null;
        }
        $jar = $app->make(\Fnlla\Cookie\CookieJar::class);
        return $jar instanceof \Fnlla\Cookie\CookieJar ? $jar : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\auth')) {
    function auth(Container $app): mixed
    {
        if (!class_exists(\Fnlla\Auth\AuthManager::class) || !$app->has(\Fnlla\Auth\AuthManager::class)) {
            return null;
        }
        $auth = $app->make(\Fnlla\Auth\AuthManager::class);
        return $auth instanceof \Fnlla\Auth\AuthManager ? $auth : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\rate_limiter')) {
    function rate_limiter(Container $app): mixed
    {
        if (!class_exists(\Fnlla\RateLimit\RateLimiter::class) || !$app->has(\Fnlla\RateLimit\RateLimiter::class)) {
            return null;
        }
        $limiter = $app->make(\Fnlla\RateLimit\RateLimiter::class);
        return $limiter instanceof \Fnlla\RateLimit\RateLimiter ? $limiter : null;
    }
}

if (!function_exists(__NAMESPACE__ . '\\db')) {
    function db(Container $app, ?string $table = null): mixed
    {
        if (class_exists(\Fnlla\Database\DatabaseManager::class) && $app->has(\Fnlla\Database\DatabaseManager::class)) {
            $manager = $app->make(\Fnlla\Database\DatabaseManager::class);
            if ($manager instanceof \Fnlla\Database\DatabaseManager) {
                return $table === null ? $manager : $manager->table($table);
            }
        }

        if (!class_exists(\Fnlla\Database\ConnectionManager::class) || !$app->has(\Fnlla\Database\ConnectionManager::class)) {
            return null;
        }

        $manager = $app->make(\Fnlla\Database\ConnectionManager::class);
        if (!$manager instanceof \Fnlla\Database\ConnectionManager) {
            return null;
        }

        if ($table === null) {
            return $manager;
        }

        if (!class_exists(\Fnlla\Database\Query::class)) {
            return null;
        }

        $query = new \Fnlla\Database\Query($manager->connection());
        return $query->table($table);
    }
}
