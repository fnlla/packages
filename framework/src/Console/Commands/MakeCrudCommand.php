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

final class MakeCrudCommand extends AbstractMakeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'make:crud';
    }

    public function getDescription(): string
    {
        return 'Scaffold a CRUD set (model, migration, resource controller, request, policy).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $name = $args[0] ?? '';
        if ($name === '') {
            $io->error('CRUD name is required.');
            return 1;
        }

        $resource = $this->studly($name);

        $model = new MakeModelCommand();
        if ($model->run([$resource], ['migration' => true], $io, $root) !== 0) {
            return 1;
        }

        $controller = new MakeControllerCommand();
        if ($controller->run([$resource], ['resource' => true], $io, $root) !== 0) {
            return 1;
        }

        $request = new MakeRequestCommand();
        if ($request->run([$resource], [], $io, $root) !== 0) {
            return 1;
        }

        $policy = new MakePolicyCommand();
        if ($policy->run([$resource], [], $io, $root) !== 0) {
            return 1;
        }

        $io->line('CRUD scaffold ready. Add routes and wire the controller.');
        return 0;
    }
}
