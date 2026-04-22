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

final class AiDocsSyncCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:docs-sync';
    }

    public function getDescription(): string
    {
        return 'Detect documentation drift by comparing config topics to docs coverage.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $configDir = $root . DIRECTORY_SEPARATOR . 'config';
        $docsDir = $root . DIRECTORY_SEPARATOR . 'documentation' . DIRECTORY_SEPARATOR . 'src';
        if (!is_dir($docsDir)) {
            $docsDir = $root . DIRECTORY_SEPARATOR . 'docs';
        }

        if (!is_dir($configDir)) {
            $io->warn('Config directory not found: ' . $configDir);
            return 0;
        }
        if (!is_dir($docsDir)) {
            $io->warn('Docs directory not found: ' . $docsDir);
            return 0;
        }

        $configFiles = glob($configDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $docFiles = glob($docsDir . DIRECTORY_SEPARATOR . '*.md') ?: [];

        $docNames = [];
        foreach ($docFiles as $doc) {
            $docNames[] = strtolower(basename($doc, '.md'));
        }

        $ignore = ['app', 'routes', 'console'];
        $missingDocs = [];

        foreach ($configFiles as $file) {
            $name = strtolower(basename($file, '.php'));
            if (in_array($name, $ignore, true)) {
                continue;
            }
            if (!in_array($name, $docNames, true)) {
                $missingDocs[] = $name;
            }
        }

        if ($missingDocs === []) {
            $io->line('Docs Sync: no missing docs detected for config topics.');
            return 0;
        }

        $io->line('Docs Sync: missing docs for config topics:');
        $docsLabel = str_replace($root . DIRECTORY_SEPARATOR, '', $docsDir);
        foreach ($missingDocs as $topic) {
            $io->line(' - ' . $docsLabel . DIRECTORY_SEPARATOR . $topic . '.md');
        }

        return 0;
    }
}
