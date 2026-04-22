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
use Fnlla\Core\ConfigRepository;
use Fnlla\Docs\DocsManager;
use Fnlla\Docs\DocsPaths;

final class DocsGenerateCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'docs:generate';
    }

    public function getDescription(): string
    {
        return 'Generate documentation snapshots into storage/docs/generated (use --publish to copy into resources/docs).';
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

        $config = ConfigRepository::fromRoot($appRoot);
        $paths = new DocsPaths($config, $appRoot);
        $manager = new DocsManager($config, $paths);

        $target = $options['target'] ?? null;
        if (!is_string($target) || trim($target) === '') {
            $target = $paths->generated();
        }

        $report = $manager->generate(['target' => $target]);
        $count = count($report['generated'] ?? []);

        $io->line('Docs generated into: ' . ($report['target'] ?? $target));
        $io->line('Pages generated: ' . $count);

        $publish = isset($options['publish']) || isset($options['p']);
        if ($publish) {
            $publishTarget = $options['publish-target'] ?? $options['publish_target'] ?? null;
            if (!is_string($publishTarget) || trim($publishTarget) === '') {
                $publishTarget = null;
            }

            $publishReport = $manager->publish([
                'source' => $report['target'] ?? $target,
                'target' => $publishTarget,
            ]);

            $publishedCount = count($publishReport['published'] ?? []);
            $io->line('Docs published into: ' . ($publishReport['target'] ?? $paths->published()));
            $io->line('Docs published: ' . $publishedCount);
        }

        return 0;
    }
}


