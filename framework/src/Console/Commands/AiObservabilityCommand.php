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

final class AiObservabilityCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:observability';
    }

    public function getDescription(): string
    {
        return 'Summarise recent logs and highlight top error signals.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $config = ConfigRepository::fromRoot($root);
        $logConfig = $config->get('log', []);
        $logPath = is_array($logConfig) ? (string) ($logConfig['path'] ?? '') : '';

        if ($logPath === '') {
            $logPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
        }

        if (!is_file($logPath)) {
            $io->warn('Log file not found: ' . $logPath);
            return 0;
        }

        $limit = isset($options['lines']) ? max(50, (int) $options['lines']) : 2000;
        $lines = $this->tailLines($logPath, $limit);

        $total = count($lines);
        $errors = 0;
        $warnings = 0;
        $exceptions = [];
        $signals = [];

        foreach ($lines as $line) {
            $lineLower = strtolower($line);
            if (str_contains($lineLower, 'error') || str_contains($lineLower, 'exception')) {
                $errors++;
            }
            if (str_contains($lineLower, 'warning')) {
                $warnings++;
            }

            if (preg_match('/([A-Z][A-Za-z0-9_\\\\]+Exception)/', $line, $match) === 1) {
                $key = $match[1];
                $exceptions[$key] = ($exceptions[$key] ?? 0) + 1;
            }

            if (str_contains($lineLower, 'sqlstate')) {
                $signals['Database errors (SQLSTATE)'] = true;
            }
            if (str_contains($lineLower, 'connection refused')) {
                $signals['Connection refused'] = true;
            }
            if (str_contains($lineLower, 'timeout')) {
                $signals['Timeouts detected'] = true;
            }
        }

        arsort($exceptions);
        $topExceptions = array_slice($exceptions, 0, 5, true);

        $io->line('Observability Digest');
        $io->line('Log: ' . $logPath);
        $io->line('Lines scanned: ' . $total);
        $io->line('Errors: ' . $errors . ' | Warnings: ' . $warnings);
        $io->line('');

        if ($topExceptions !== []) {
            $io->line('Top exceptions:');
            foreach ($topExceptions as $name => $count) {
                $io->line(' - ' . $name . ': ' . $count);
            }
            $io->line('');
        }

        if ($signals !== []) {
            $io->line('Signals:');
            foreach (array_keys($signals) as $signal) {
                $io->line(' - ' . $signal);
            }
        } else {
            $io->line('Signals: none detected.');
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function tailLines(string $path, int $limit): array
    {
        $content = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($content === false) {
            return [];
        }

        $total = count($content);
        if ($total <= $limit) {
            return $content;
        }

        return array_slice($content, $total - $limit);
    }
}
