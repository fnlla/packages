<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs\Commands;

use Fnlla\Console\CommandInterface;
use Fnlla\Console\ConsoleIO;
use RuntimeException;

final class DocsSyncCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'docs:sync';
    }

    public function getDescription(): string
    {
        return 'Sync docs from the monorepo root into the app resources/docs folder.';
    }

    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $appRoot = $options['app'] ?? $options['a'] ?? $root;
        if (!is_string($appRoot) || trim($appRoot) === '') {
            $io->error('Missing --app=PATH.');
            return 1;
        }

        $appRoot = rtrim($appRoot, '/\\');
        if (!is_dir($appRoot)) {
            $io->error('App path not found: ' . $appRoot);
            return 1;
        }

        $repoRoot = dirname($appRoot);
        $defaultSource = $repoRoot . DIRECTORY_SEPARATOR . 'docs';
        if (!is_dir($defaultSource)) {
            $defaultSource = $appRoot . DIRECTORY_SEPARATOR . 'docs';
        }

        $source = $options['source'] ?? $defaultSource;
        if (!is_string($source) || trim($source) === '') {
            $io->error('Docs source not found.');
            return 1;
        }

        $source = rtrim($source, '/\\');
        if (!is_dir($source)) {
            $io->error('Docs source not found: ' . $source);
            return 1;
        }

        $target = $options['target'] ?? null;
        if (!is_string($target) || trim($target) === '') {
            $target = $appRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'docs';
        }
        $target = rtrim((string) $target, '/\\');

        $clean = isset($options['clean']);

        try {
            $count = $this->sync($source, $target, $clean);
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }

        $io->line('Docs synced: ' . $count . ' files');
        $io->line('Source: ' . $source);
        $io->line('Target: ' . $target);

        return 0;
    }

    private function sync(string $source, string $target, bool $clean): int
    {
        if ($clean) {
            $this->deleteDir($target);
        }

        $this->ensureDir($target);

        $copied = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(str_replace($source, '', $item->getPathname()), '/\\');
            $targetPath = $target . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                $this->ensureDir($targetPath);
                continue;
            }

            $this->ensureDir(dirname($targetPath));
            if (@copy($item->getPathname(), $targetPath)) {
                $copied++;
            }
        }

        return $copied;
    }

    private function ensureDir(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create directory: ' . $path);
        }
    }

    private function deleteDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
