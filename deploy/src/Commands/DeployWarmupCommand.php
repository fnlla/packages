<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Deploy\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;

final class DeployWarmupCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'deploy:warmup';
    }

    public function getDescription(): string
    {
        return 'Warm up caches and provider discovery.';
    }

    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $root = rtrim($root, '/\\');
        $bin = $root . '/bin/fnlla-discover';

        if (is_file($bin)) {
            $io->line('Running provider discovery...');
            $status = $this->runCommand(PHP_BINARY . ' ' . escapeshellarg($bin), $root, $io);
            if ($status !== 0) {
                return $status;
            }
        } else {
            $io->line('No fnlla-discover script found, skipping.');
        }

        $io->line('Warmup complete.');
        return 0;
    }

    private function runCommand(string $command, string $cwd, ConsoleIO $io): int
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $cwd);
        if (!is_resource($process)) {
            $io->error('Failed to start command: ' . $command);
            return 1;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($stdout !== '') {
            $io->line(trim($stdout));
        }
        if ($stderr !== '') {
            $io->error(trim($stderr));
        }

        return $exitCode;
    }
}
