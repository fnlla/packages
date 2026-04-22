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

final class MakeControllerCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:controller';
    }

    public function getDescription(): string
    {
        return 'Create a controller class.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Controller name is required.');
            return 1;
        }

        [$namespace, $class, $path] = $this->resolveClass(
            $name,
            'App\\Controllers',
            $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers',
            'Controller'
        );

        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        $template .= isset($options['resource']) ? 'controller_resource.stub' : 'controller.stub';

        $contents = $this->renderTemplate($template, [
            'namespace' => $namespace,
            'class' => $class,
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
