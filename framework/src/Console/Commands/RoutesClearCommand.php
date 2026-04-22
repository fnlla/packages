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
use Fnlla\Core\ConfigRepository;
use RuntimeException;

final class RoutesClearCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'routes:clear';
    }

    public function getDescription(): string
    {
        return 'Remove the routes cache file.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        try {
            $config = ConfigRepository::fromRoot($root);
            $path = (string) $config->get('routes_cache', '');
            if ($path === '') {
                $path = $root . '/storage/cache/routes.php';
            }

            if (!is_file($path)) {
                $io->line('Routes cache not found: ' . $path);
                return 0;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove routes cache: ' . $path);
            }

            $io->line('Routes cache cleared: ' . $path);
            return 0;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
