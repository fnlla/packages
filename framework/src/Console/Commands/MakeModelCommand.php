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
use RuntimeException;

final class MakeModelCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:model';
    }

    public function getDescription(): string
    {
        return 'Create a model class (optionally with migration and factory).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('Model name is required.');
            return 1;
        }

        $modelsDir = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models';
        [$namespace, $class, $path] = $this->resolveClass($name, 'App\\Models', $modelsDir);

        $template = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Fnlla' . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'model.stub';
        if (!is_file($template)) {
            $template = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'model.stub';
        }

        try {
            $contents = $this->renderTemplate($template, [
                'namespace' => $namespace,
                'class' => $class,
            ]);
            $this->writeFile($io, $path, $contents);

            if (!empty($options['migration']) || !empty($options['m'])) {
                $table = $this->pluralise($this->snake($class));
                $migration = new MakeMigrationCommand();
                $migration->run(['create_' . $table . '_table'], [], $io, $root);
            }

            if (!empty($options['factory']) || !empty($options['f'])) {
                $this->createFactory($io, $root, $namespace, $class);
            }
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    private function createFactory(ConsoleIO $io, string $root, string $modelNamespace, string $modelClass): void
    {
        $factoriesDir = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'factories';
        [$factoryNamespace, $class, $path] = $this->resolveClass($modelClass . 'Factory', 'Database\\Factories', $factoriesDir, 'Factory');

        $template = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'Fnlla' . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'factory.stub';
        if (!is_file($template)) {
            $template = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'factory.stub';
        }

        $contents = $this->renderTemplate($template, [
            'namespace' => $factoryNamespace,
            'class' => $class,
            'modelClass' => $modelClass,
            'modelFqcn' => $modelNamespace . '\\' . $modelClass,
        ]);

        $this->writeFile($io, $path, $contents);
    }

    private function pluralise(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        if (str_ends_with($value, 'y') && !preg_match('/[aeiou]y$/', $value)) {
            return substr($value, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/', $value)) {
            return $value . 'es';
        }
        return $value . 's';
    }
}
