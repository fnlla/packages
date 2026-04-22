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

final class MakeModuleCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:module';
    }

    public function getDescription(): string
    {
        return 'Create a module skeleton (Controllers/Requests/Policies/Jobs/Commands/Migrations/Views).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Module name is required.');
            return 1;
        }

        $module = $this->studly($name);
        $base = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $module;
        $dirs = [
            'Controllers',
            'Requests',
            'Policies',
            'Jobs',
            'Commands',
            'Migrations',
            'Views',
        ];

        foreach ($dirs as $dir) {
            $path = $base . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) {
                $io->error('Unable to create directory: ' . $path);
                return 1;
            }
        }

        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'module_provider.stub';
        $namespace = 'App\\Modules\\' . $module;
        $class = $module . 'ServiceProvider';
        $contents = $this->renderTemplate($template, [
            'namespace' => $namespace,
            'class' => $class,
        ]);

        $target = $base . DIRECTORY_SEPARATOR . $class . '.php';
        try {
            $this->writeFile($io, $target, $contents);
        } catch (\RuntimeException $e) {
            $io->warn($e->getMessage());
        }

        return 0;
    }
}
