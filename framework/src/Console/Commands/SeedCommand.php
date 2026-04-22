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
use Fnlla\Console\SeederInterface;
use Fnlla\Database\ConnectionManager;
use RuntimeException;

final class SeedCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'seed';
    }

    public function getDescription(): string
    {
        return 'Run database seeders.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $seederPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'seeders' . DIRECTORY_SEPARATOR . 'DatabaseSeeder.php';
        if (!is_file($seederPath)) {
            $io->error('Seeder not found: ' . $seederPath);
            return 1;
        }

        try {
            $config = [];
            $configFile = $this->resolveDatabaseConfigFile($root);
            if ($configFile !== null) {
                $loaded = require $configFile;
                if (is_array($loaded)) {
                    $config = $loaded;
                }
            }

            $manager = new ConnectionManager($config);
            $pdo = $manager->connection();
            if (class_exists(\Fnlla\Orm\Model::class)) {
                \Fnlla\Orm\Model::setConnectionManager($manager);
            }

            $seeder = require $seederPath;
            if (is_callable($seeder)) {
                $ref = new \ReflectionFunction(\Closure::fromCallable($seeder));
                if ($ref->getNumberOfParameters() > 0) {
                    $seeder($pdo);
                } else {
                    $seeder();
                }
                $io->line('Seed executed (callable).');
                return 0;
            }

            if (is_string($seeder) && class_exists($seeder)) {
                $instance = new $seeder();
                if ($instance instanceof SeederInterface) {
                    $instance->run($pdo);
                    $io->line('Seed executed (SeederInterface).');
                    return 0;
                }
                if (method_exists($instance, 'run')) {
                    $instance->run($pdo);
                    $io->line('Seed executed (run method).');
                    return 0;
                }
            }

            if (is_object($seeder) && method_exists($seeder, 'run')) {
                $seeder->run($pdo);
                $io->line('Seed executed (run method).');
                return 0;
            }

            throw new RuntimeException('Invalid DatabaseSeeder.php format.');
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }

    private function resolveDatabaseConfigFile(string $root): ?string
    {
        $candidates = [
            'config/database/database.php',
            'config/database.php',
        ];

        foreach ($candidates as $relativePath) {
            $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
            $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
