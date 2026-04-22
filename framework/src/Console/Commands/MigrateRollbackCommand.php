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

final class MigrateRollbackCommand implements CommandInterface
{
    use DatabaseCommandTrait;

    public function getName(): string
    {
        return 'migrate:rollback';
    }

    public function getDescription(): string
    {
        return 'Rollback the last migration batch.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $steps = (int) ($options['steps'] ?? 1);
        $steps = $steps > 0 ? $steps : 1;
        $path = is_string($options['path'] ?? null) ? (string) $options['path'] : null;

        try {
            $runner = $this->makeRunner($root, $path);
            $rolled = $runner->rollback($steps);
            $io->line('Migrations rolled back: ' . count($rolled));
            foreach ($rolled as $name) {
                $io->line(' - ' . $name);
            }
            return 0;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
