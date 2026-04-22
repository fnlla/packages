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

final class MigrateCommand implements CommandInterface
{
    use DatabaseCommandTrait;

    public function getName(): string
    {
        return 'migrate';
    }

    public function getDescription(): string
    {
        return 'Run database migrations.';
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
            $executed = $runner->migrate();
            $io->line('Migrations executed: ' . count($executed));
            foreach ($executed as $name) {
                $io->line(' - ' . $name);
            }
            return 0;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
