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

final class MigrateStatusCommand implements CommandInterface
{
    use DatabaseCommandTrait;

    public function getName(): string
    {
        return 'migrate:status';
    }

    public function getDescription(): string
    {
        return 'Show migration status.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $path = is_string($options['path'] ?? null) ? (string) $options['path'] : null;

        try {
            $runner = $this->makeRunner($root, $path);
            $status = $runner->status();
            if ($status === []) {
                $io->line('No migrations found.');
                return 0;
            }

            foreach ($status as $row) {
                $io->line(($row['ran'] ? '[X] ' : '[ ] ') . $row['migration']);
            }
            return 0;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
