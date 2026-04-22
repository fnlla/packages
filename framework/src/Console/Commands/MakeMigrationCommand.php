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

final class MakeMigrationCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:migration';
    }

    public function getDescription(): string
    {
        return 'Create a migration file.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Migration name is required.');
            return 1;
        }

        $slug = $this->snake($name);
        if ($slug === '') {
            $io->error('Migration name is invalid.');
            return 1;
        }

        $timestamp = gmdate('YmdHis');
        $filename = $timestamp . '_' . $slug . '.php';
        $dir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'migration.stub';
        $table = 'table';
        if (preg_match('/^create_(.+)_table$/', $slug, $matches) === 1) {
            $table = $matches[1];
        }
        $contents = $this->renderTemplate($template, [
            'table' => $table,
        ]);

        try {
            $this->writeFile($io, $path, $contents);
            return 0;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
