<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Fnlla\Contracts\Http\KernelInterface;
use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Database\MigrationRunner;
use Fnlla\Http\Request;
use Fnlla\Http\Response;
use Fnlla\Http\Stream;
use Fnlla\Http\Uri;
use Fnlla\Support\Psr\Http\Message\ResponseInterface;
use Fnlla\Session\SessionInterface;
use Fnlla\Auth\AuthManager;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected string $appRoot = '';
    protected ?KernelInterface $kernel = null;
    protected ?Container $app = null;
    protected array $cookies = [];
    protected array $headers = [];
    protected bool $csrfEnabled = false;
    protected ?string $csrfToken = null;
    protected bool $useDatabase = true;

    public function setUp(): void
    {
        parent::setUp();
        $this->bootApplication();
        if ($this->useDatabase) {
            $this->refreshDatabase();
        }
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    protected function app(): Container
    {
        if ($this->app instanceof Container) {
            return $this->app;
        }
        if (isset($GLOBALS['Fnlla_app']) && $GLOBALS['Fnlla_app'] instanceof Container) {
            return $GLOBALS['Fnlla_app'];
        }
        throw new RuntimeException('Application container is not available.');
    }

    protected function bootApplication(): void
    {
        if ($this->kernel instanceof KernelInterface) {
            return;
        }

        $root = $this->appRoot !== '' ? $this->appRoot : (string) (getenv('APP_ROOT') ?: getcwd());
        $root = rtrim($root, '/\\');
        $this->appRoot = $root;

        $this->setEnv([
            'APP_ENV' => 'test',
            'APP_DEBUG' => '1',
            'APP_ROOT' => $root,
            'CSRF_ENABLED' => $this->csrfEnabled ? '1' : '0',
            'DB_CONNECTION' => 'sqlite',
            'DB_PATH' => ':memory:',
        ]);

        $bootstrap = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';
        if (!is_file($bootstrap)) {
            throw new RuntimeException('Bootstrap file not found: ' . $bootstrap);
        }

        $kernel = require $bootstrap;
        if (!$kernel instanceof KernelInterface) {
            throw new RuntimeException('Bootstrap must return a KernelInterface.');
        }

        $this->kernel = $kernel;
        $this->app = $GLOBALS['Fnlla_app'] ?? null;
    }

    protected function refreshDatabase(): void
    {
        $app = $this->app();
        $config = method_exists($app, 'config') ? $app->config()->get('database', []) : [];
        if (!is_array($config)) {
            $config = [];
        }

        $manager = new ConnectionManager($config);
        $app->instance(ConnectionManager::class, $manager);
        if (class_exists(\Fnlla\Orm\Model::class)) {
            \Fnlla\Orm\Model::setConnectionManager($manager);
        }

        $migrationsPath = $this->appRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $hasMigrations = is_dir($migrationsPath);
        if ($hasMigrations) {
            $files = glob($migrationsPath . DIRECTORY_SEPARATOR . '*.php');
            $hasMigrations = is_array($files) && $files !== [];
        }

        if ($hasMigrations) {
            $runner = new MigrationRunner($manager, $migrationsPath);
            $runner->migrate();
            return;
        }

        $driver = strtolower((string) getenv('DB_CONNECTION'));
        $schemaPath = $this->appRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR
            . ($driver === 'sqlite' ? 'schema.sqlite.sql' : 'schema.sql');
        if (!is_file($schemaPath)) {
            $schemaPath = $this->appRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        }
        if (!is_file($schemaPath)) {
            throw new RuntimeException('No migrations or schema.sql found for test database.');
        }

        $schema = file_get_contents($schemaPath);
        if (!is_string($schema) || trim($schema) === '') {
            throw new RuntimeException('Schema file is empty: ' . $schemaPath);
        }

        $pdo = $manager->connection();
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    protected function actingAs(mixed $user): self
    {
        $app = $this->app();
        if (!$app->has(AuthManager::class)) {
            throw new RuntimeException('AuthManager is not available.');
        }

        $auth = $app->make(AuthManager::class);
        if (!$auth instanceof AuthManager) {
            throw new RuntimeException('AuthManager is not available.');
        }

        $this->ensureSessionStarted();
        $auth->login($user);
        $this->syncSessionCookie();
        return $this;
    }

    protected function withCsrf(): self
    {
        $this->csrfEnabled = true;
        $this->setEnv(['CSRF_ENABLED' => '1']);
        $this->ensureSessionStarted();
        if ($this->csrfToken === null && function_exists('csrf_token')) {
            $this->csrfToken = csrf_token();
        }
        return $this;
    }

    protected function withoutCsrf(): self
    {
        $this->csrfEnabled = false;
        $this->setEnv(['CSRF_ENABLED' => '0']);
        $this->csrfToken = null;
        return $this;
    }

    protected function get(string $url, array $headers = []): TestResponse
    {
        return $this->request('GET', $url, [], $headers);
    }

    protected function post(string $url, array $data = [], array $headers = []): TestResponse
    {
        if ($this->csrfEnabled) {
            $token = $this->csrfToken ?? (function_exists('csrf_token') ? csrf_token() : null);
            if (is_string($token) && $token !== '' && !array_key_exists('_token', $data)) {
                $data['_token'] = $token;
            }
        }
        return $this->request('POST', $url, $data, $headers);
    }

    protected function postJson(string $url, array $data = [], array $headers = []): TestResponse
    {
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $url, $data, $headers, true);
    }

    private function request(string $method, string $url, array $data, array $headers, bool $json = false): TestResponse
    {
        $kernel = $this->kernel;
        if (!$kernel instanceof KernelInterface) {
            throw new RuntimeException('Kernel not booted.');
        }

        $headers = array_merge($this->headers, $headers);

        [$path, $query] = $this->parseUrl($url);
        $uri = new Uri('http://localhost' . $path . ($query !== '' ? '?' . $query : ''));

        $server = [
            'REQUEST_METHOD' => $method,
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $body = $json ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $stream = Stream::fromString(is_string($body) ? $body : '');

        $request = new Request($method, $uri, $headers, $stream, $server);
        $request = $request->withCookieParams($this->cookies);
        $request = $request->withQueryParams($this->parseQuery($query));
        $request = $request->withParsedBody($data);

        $response = $kernel->handle($request);
        if (!$response instanceof Response) {
            $body = (string) $response->getBody();
            $response = new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                Stream::fromString($body),
                $response->getReasonPhrase()
            );
        }

        $this->updateCookies($response);

        $session = $this->resolveSession();
        return new TestResponse($response, $session);
    }

    private function parseUrl(string $url): array
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';
        return [$path, $query];
    }

    private function parseQuery(string $query): array
    {
        if ($query === '') {
            return [];
        }
        parse_str($query, $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    private function updateCookies(Response $response): void
    {
        $cookies = $response->getHeader('Set-Cookie');
        foreach ($cookies as $cookieLine) {
            $parts = explode(';', $cookieLine);
            $pair = explode('=', trim($parts[0]), 2);
            $name = urldecode($pair[0] ?? '');
            $value = urldecode($pair[1] ?? '');
            if ($name !== '') {
                $this->cookies[$name] = $value;
            }
        }
    }

    private function resolveSession(): ?SessionInterface
    {
        $app = $this->app();
        if (!interface_exists(SessionInterface::class) || !$app->has(SessionInterface::class)) {
            return null;
        }

        $session = $app->make(SessionInterface::class);
        return $session instanceof SessionInterface ? $session : null;
    }

    private function ensureSessionStarted(): void
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return;
        }
        if (method_exists($session, 'start')) {
            $cookieName = method_exists($session, 'cookieName') ? $session->cookieName() : 'Fnlla_session';
            $sessionId = $this->cookies[$cookieName] ?? null;
            $session->start(is_string($sessionId) ? $sessionId : null);
        }
    }

    private function syncSessionCookie(): void
    {
        $session = $this->resolveSession();
        if ($session === null) {
            return;
        }
        if (method_exists($session, 'cookieHeader')) {
            $this->updateCookies(Response::text('', 200, ['Set-Cookie' => $session->cookieHeader()]));
        }
    }

    private function setEnv(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $value = (string) $value;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
