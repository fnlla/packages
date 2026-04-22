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

final class MakeRepositoryCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:repository';
    }

    public function getDescription(): string
    {
        return 'Create a repository class.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Repository name is required.');
            return 1;
        }

        [$namespace, $class, $path] = $this->resolveClass(
            $name,
            'App\\Repositories',
            $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Repositories',
            'Repository'
        );

        $template = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Fnlla' . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'repository.stub';
        if (!is_file($template)) {
            $template = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'repository.stub';
        }

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
