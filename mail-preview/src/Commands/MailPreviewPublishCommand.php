<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\MailPreview\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;

final class MailPreviewPublishCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'mail-preview:publish';
    }

    public function getDescription(): string
    {
        return 'Publish mail preview templates into the app.';
    }

    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $appRoot = $options['app'] ?? $options['a'] ?? $root;
        $force = isset($options['force']) || isset($options['f']);
        $dryRun = isset($options['dry-run']) || isset($options['dry_run']) || isset($options['n']);

        $source = dirname(__DIR__, 2) . '/stubs';
        if (!is_dir($source)) {
            $io->error('Mail preview stubs not found: ' . $source);
            return 1;
        }

        if (!is_string($appRoot) || trim($appRoot) === '') {
            $io->error('Missing --app=PATH.');
            return 1;
        }

        $appRoot = rtrim($appRoot, '/\\');
        if (!is_dir($appRoot)) {
            $io->error('App path not found: ' . $appRoot);
            return 1;
        }

        $files = $this->collectFiles($source);
        $copied = 0;
        $skipped = 0;

        foreach ($files as $relative) {
            $from = $source . '/' . $relative;
            $to = $appRoot . '/' . $relative;

            if (is_file($to) && !$force) {
                $skipped++;
                $io->line('SKIP ' . $relative);
                continue;
            }

            $dir = dirname($to);
            if (!is_dir($dir) && !$dryRun) {
                mkdir($dir, 0777, true);
            }

            if (!$dryRun) {
                if (!copy($from, $to)) {
                    $io->error('Failed to copy ' . $relative);
                    return 1;
                }
            }

            $copied++;
            $io->line('COPY ' . $relative);
        }

        $io->line('Done. Copied ' . $copied . ', skipped ' . $skipped . '.');
        return 0;
    }

    private function collectFiles(string $base): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $fileInfo->getPathname());
            $relative = ltrim(substr($path, strlen(str_replace('\\', '/', $base))), '/');
            if ($relative === '') {
                continue;
            }
            $files[] = $relative;
        }

        sort($files);
        return $files;
    }
}
