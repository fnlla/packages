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

final class MakePolicyCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:policy';
    }

    public function getDescription(): string
    {
        return 'Create a policy class.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Policy name is required.');
            return 1;
        }

        [$namespace, $class, $path] = $this->resolveClass(
            $name,
            'App\\Policies',
            $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Policies',
            'Policy'
        );

        $template = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'policy.stub';
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
