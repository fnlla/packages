<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;
use Fnlla\Core\ConfigRepository;
use Fnlla\Database\ConnectionManager;
use Fnlla\Database\MigrationRunner;
use RuntimeException;
use PDO;
use Throwable;

final class DatabaseBootstrapCommand implements CommandInterface
{
    use DatabaseCommandTrait;

    public function getName(): string
    {
        return 'db:bootstrap';
    }

    public function getDescription(): string
    {
        return 'Create the database if needed and run migrations.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $appRoot = $options['app'] ?? $options['a'] ?? $root;
        if (!is_string($appRoot) || trim($appRoot) === '') {
            $io->error('Missing --app=PATH.');
            return 1;
        }

        $appRoot = rtrim($appRoot, '/\\');
        if (!is_dir($appRoot)) {
            $io->error('App path not found: ' . $appRoot);
            return 1;
        }

        $this->bootEnv($appRoot);

        if (!class_exists(ConnectionManager::class)) {
            $io->error('Database core module is not available. Ensure fnlla/framework is installed.');
            return 1;
        }

        $config = $this->loadDatabaseConfig($appRoot);
        $resolved = $this->resolveConfig($config);
        $driver = $resolved['driver'];

        if ($driver === 'sqlite') {
            $this->ensureSqlitePath($resolved['path'], $io);
        } else {
            if (!$this->ensureDatabase($config, $resolved, $options, $io)) {
                return 1;
            }
        }

        $paths = $this->resolveMigrationPaths($appRoot, $options);
        $total = 0;

        foreach ($paths as $label => $path) {
            try {
                $runner = $this->makeRunner($appRoot, $path);
                $executed = $runner->migrate();
                $total += count($executed);
                $io->line($label . ': ' . count($executed) . ' migrations executed.');
            } catch (RuntimeException $e) {
                $io->error($label . ': ' . $e->getMessage());
                return 1;
            }
        }

        $io->line('Database bootstrap complete. Total migrations executed: ' . $total . '.');
        return 0;
    }

    private function bootEnv(string $root): void
    {
        $envPath = $root . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath)) {
            return;
        }

        try {
            $dotenv = new \Fnlla\Support\Dotenv();
            if (method_exists($dotenv, 'usePutenv')) {
                $dotenv->usePutenv(true);
            }
            if (method_exists($dotenv, 'bootEnv')) {
                $dotenv->bootEnv($envPath);
            } elseif (method_exists($dotenv, 'loadEnv')) {
                $dotenv->loadEnv($envPath);
            } else {
                $dotenv->load($envPath);
            }
        } catch (Throwable) {
            // Best-effort env load.
        }
    }

    private function loadDatabaseConfig(string $root): array
    {
        $config = [];
        $configFile = $this->resolveDatabaseConfigFile($root);
        if ($configFile !== null) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        if ($config === []) {
            $repo = ConfigRepository::fromRoot($root);
            $loaded = $repo->get('database', []);
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        return $config;
    }

    private function resolveConfig(array $config): array
    {
        $default = $config['default'] ?? null;
        $connections = $config['connections'] ?? null;
        if (is_string($default) && is_array($connections) && isset($connections[$default]) && is_array($connections[$default])) {
            $config = $connections[$default];
        }

        $driver = '';
        if (array_key_exists('driver', $config)) {
            $driver = (string) $config['driver'];
        }
        if ($driver === '' && array_key_exists('connection', $config)) {
            $driver = (string) $config['connection'];
        }
        if ($driver === '') {
            $envDriver = getenv('DB_CONNECTION');
            $driver = $envDriver !== false ? (string) $envDriver : '';
        }
        if ($driver === '') {
            $driver = 'mysql';
        }
        $driver = strtolower($driver);

        if ($driver === '') {
            throw new RuntimeException('Database driver is not configured.');
        }

        if ($driver === 'sqlite') {
            $path = array_key_exists('path', $config) ? (string) $config['path'] : '';
            if ($path === '' && array_key_exists('database', $config)) {
                $path = (string) $config['database'];
            }
            if ($path === '') {
                $envPath = getenv('DB_PATH');
                $path = $envPath !== false ? (string) $envPath : '';
            }
            if ($path === '') {
                $envPath = getenv('DB_DATABASE');
                $path = $envPath !== false ? (string) $envPath : '';
            }

            $path = $path === '' ? ':memory:' : $path;

            return [
                'driver' => 'sqlite',
                'path' => $path,
            ];
        }

        $host = array_key_exists('host', $config) ? (string) $config['host'] : '';
        if ($host === '') {
            $envHost = getenv('DB_HOST');
            $host = $envHost !== false ? (string) $envHost : '127.0.0.1';
        }

        $port = array_key_exists('port', $config) ? (string) $config['port'] : '';
        if ($port === '') {
            $envPort = getenv('DB_PORT');
            $port = $envPort !== false ? (string) $envPort : ($driver === 'pgsql' ? '5432' : '3306');
        }

        $database = array_key_exists('database', $config) ? (string) $config['database'] : '';
        if ($database === '') {
            $envDatabase = getenv('DB_DATABASE');
            $database = $envDatabase !== false ? (string) $envDatabase : '';
        }

        $username = array_key_exists('username', $config) ? (string) $config['username'] : '';
        if ($username === '') {
            $envUsername = getenv('DB_USERNAME');
            $username = $envUsername !== false ? (string) $envUsername : '';
        }

        $password = array_key_exists('password', $config) ? (string) $config['password'] : '';
        if ($password === '') {
            $envPassword = getenv('DB_PASSWORD');
            $password = $envPassword !== false ? (string) $envPassword : '';
        }

        $charset = array_key_exists('charset', $config) ? (string) $config['charset'] : '';
        if ($charset === '') {
            $envCharset = getenv('DB_CHARSET');
            $charset = $envCharset !== false ? (string) $envCharset : 'utf8mb4';
        }

        if ($database === '') {
            throw new RuntimeException('Database name is not configured.');
        }

        return [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => $charset,
        ];
    }

    private function ensureSqlitePath(string $path, ConsoleIO $io): void
    {
        if ($path === ':memory:') {
            $io->line('SQLite: using in-memory database.');
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!is_file($path)) {
            $handle = @fopen($path, 'wb');
            if ($handle !== false) {
                fclose($handle);
            }
            $io->line('SQLite: created database file at ' . $path);
        }
    }

    private function ensureDatabase(array $config, array $resolved, array $options, ConsoleIO $io): bool
    {
        try {
            $manager = new ConnectionManager($config);
            $manager->connection();
            return true;
        } catch (Throwable $e) {
            $io->line('Database connection failed. Attempting to create database...');
            $allowCreate = $this->parseBool($options['create'] ?? null, true);
            $noCreate = isset($options['no-create']) || isset($options['no_create']);
            if ($noCreate) {
                $allowCreate = false;
            }

            if (!$allowCreate) {
                $io->error('Database is not reachable and creation is disabled.');
                $io->error('Set DB_* correctly or run db:bootstrap with --create.');
                return false;
            }

            if (!$this->createDatabase($resolved, $options, $io)) {
                return false;
            }

            try {
                $manager = new ConnectionManager($config);
                $manager->connection();
                return true;
            } catch (Throwable $retry) {
                $io->error('Database still not reachable after create attempt: ' . $retry->getMessage());
                return false;
            }
        }
    }

    private function createDatabase(array $resolved, array $options, ConsoleIO $io): bool
    {
        $driver = (string) ($resolved['driver'] ?? '');
        $database = (string) ($resolved['database'] ?? '');
        $host = (string) ($resolved['host'] ?? '127.0.0.1');
        $port = (string) ($resolved['port'] ?? ($driver === 'pgsql' ? '5432' : '3306'));
        $charset = (string) ($resolved['charset'] ?? 'utf8mb4');

        if ($database === '') {
            $io->error('Database name is not configured.');
            return false;
        }

        $rootUser = '';
        if (array_key_exists('root-user', $options)) {
            $rootUser = (string) $options['root-user'];
        }
        if ($rootUser === '' && array_key_exists('root_user', $options)) {
            $rootUser = (string) $options['root_user'];
        }
        if ($rootUser === '') {
            $envRootUser = getenv('DB_ROOT_USERNAME');
            $rootUser = $envRootUser !== false ? (string) $envRootUser : '';
        }
        if ($rootUser === '') {
            $rootUser = (string) ($resolved['username'] ?? '');
        }

        $rootPass = '';
        if (array_key_exists('root-pass', $options)) {
            $rootPass = (string) $options['root-pass'];
        }
        if ($rootPass === '' && array_key_exists('root_password', $options)) {
            $rootPass = (string) $options['root_password'];
        }
        if ($rootPass === '') {
            $envRootPass = getenv('DB_ROOT_PASSWORD');
            $rootPass = $envRootPass !== false ? (string) $envRootPass : '';
        }
        if ($rootPass === '') {
            $rootPass = (string) ($resolved['password'] ?? '');
        }

        if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
            $rootDb = '';
            if (array_key_exists('root-db', $options)) {
                $rootDb = (string) $options['root-db'];
            }
            if ($rootDb === '' && array_key_exists('root_database', $options)) {
                $rootDb = (string) $options['root_database'];
            }
            if ($rootDb === '') {
                $envRootDb = getenv('DB_ROOT_DATABASE');
                $rootDb = $envRootDb !== false ? (string) $envRootDb : '';
            }
            if ($rootDb === '') {
                $rootDb = 'postgres';
            }
            $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $rootDb;
        } else {
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';charset=' . $charset;
        }

        try {
            $pdo = new PDO($dsn, $rootUser, $rootPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
                $stmt->execute([$database]);
                $exists = (bool) $stmt->fetchColumn();
                $stmt->closeCursor();
                if (!$exists) {
                    $safe = str_replace('"', '""', $database);
                    $pdo->exec('CREATE DATABASE "' . $safe . '"');
                }
            } else {
                $safe = str_replace('`', '``', $database);
                $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safe . '` CHARACTER SET ' . $charset);
            }

            $io->line('Database ready: ' . $database);
            return true;
        } catch (Throwable $e) {
            $io->error('Failed to create database: ' . $e->getMessage());
            $io->error('Check DB_ROOT_USERNAME/DB_ROOT_PASSWORD or grant CREATE DATABASE privileges.');
            return false;
        }
    }

    private function resolveMigrationPaths(string $root, array $options): array
    {
        $paths = [];
        $mainPath = is_string($options['path'] ?? null) ? (string) $options['path'] : null;
        $paths['App'] = $mainPath;
        return $paths;
    }

    private function parseBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $default,
            };
        }

        return $default;
    }
}
