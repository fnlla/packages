<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Deploy\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;

final class DeployHealthCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'deploy:health';
    }

    public function getDescription(): string
    {
        return 'Run basic deploy health checks.';
    }

    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $errors = [];
        $root = rtrim($root, '/\\');

        if (!is_file($root . '/public/index.php')) {
            $errors[] = 'Missing public/index.php.';
        }
        if (!is_dir($root . '/storage')) {
            $errors[] = 'Missing storage/ directory.';
        } elseif (!is_writable($root . '/storage')) {
            $errors[] = 'storage/ is not writable.';
        }
        if (!is_dir($root . '/bootstrap/cache')) {
            $errors[] = 'Missing bootstrap/cache directory.';
        } elseif (!is_writable($root . '/bootstrap/cache')) {
            $errors[] = 'bootstrap/cache is not writable.';
        }
        if (!is_file($root . '/vendor/autoload.php')) {
            $errors[] = 'Missing vendor/autoload.php (run composer install).';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $io->error($error);
            }
            return 1;
        }

        $io->line('Deploy health checks OK.');
        return 0;
    }
}
