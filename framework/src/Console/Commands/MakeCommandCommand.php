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

final class MakeCommandCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:command';
    }

    public function getDescription(): string
    {
        return 'Create a CLI command class.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Command name is required.');
            return 1;
        }

        [$namespace, $class, $path] = $this->resolveClass(
            $name,
            'App\\Commands',
            $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Commands',
            'Command'
        );

        $commandName = $options['name'] ?? $this->snake(str_replace('Command', '', $class));
        $commandName = str_replace('_', ':', $commandName);

        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'command.stub';
        $contents = $this->renderTemplate($template, [
            'namespace' => $namespace,
            'class' => $class,
            'command' => $commandName,
            'description' => 'App command: ' . $commandName,
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
