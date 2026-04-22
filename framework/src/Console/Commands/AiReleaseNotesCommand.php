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

final class AiReleaseNotesCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:release-notes';
    }

    public function getDescription(): string
    {
        return 'Draft release notes from the CHANGELOG (deterministic).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $version = (string) ($options['version'] ?? $options['v'] ?? 'Unreleased');
        $changelog = (string) ($options['changelog'] ?? $root . '/CHANGELOG.md');
        $output = (string) ($options['output'] ?? $options['out'] ?? ($root . '/storage/ai/release-notes-' . $this->slug($version) . '.md'));
        $dry = isset($options['dry']) || isset($options['n']);

        if (!is_file($changelog)) {
            $io->error('CHANGELOG not found: ' . $changelog);
            return 1;
        }

        $section = $this->extractSection($changelog, $version);
        if ($section === null) {
            $io->error('Version section not found in CHANGELOG: ' . $version);
            return 1;
        }

        $notes = $this->formatNotes($version, $section);

        if ($dry) {
            $io->line($notes);
            return 0;
        }

        $dir = dirname($output);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $io->error('Unable to create output directory: ' . $dir);
            return 1;
        }

        if (@file_put_contents($output, $notes) === false) {
            $io->error('Unable to write release notes: ' . $output);
            return 1;
        }

        $io->line('Release notes written to ' . $output);
        return 0;
    }

    private function extractSection(string $path, string $version): ?array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $target = strtolower(trim($version));
        $in = false;
        $section = [];

        foreach ($lines as $line) {
            if (preg_match('/^##\\s*\\[(.+?)\\]/', $line, $matches)) {
                $header = strtolower(trim($matches[1]));
                if ($in) {
                    break;
                }
                if ($header === strtolower(trim($target))) {
                    $in = true;
                }
                continue;
            }

            if (preg_match('/^##\\s+unreleased/i', $line)) {
                if ($in) {
                    break;
                }
                if (strtolower($target) === 'unreleased') {
                    $in = true;
                }
                continue;
            }

            if ($in) {
                $section[] = $line;
            }
        }

        return $in ? $section : null;
    }

    private function formatNotes(string $version, array $section): string
    {
        $lines = [
            '# Release Notes: ' . $version,
            '',
        ];

        $clean = array_values(array_filter($section, fn (string $line): bool => trim($line) !== ''));
        if ($clean === []) {
            $lines[] = '_No changes recorded._';
            $lines[] = '';
            return implode("\n", $lines);
        }

        foreach ($clean as $line) {
            $lines[] = $line;
        }
        $lines[] = '';
        $lines[] = 'Generated from CHANGELOG. Edit and polish before publishing.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : 'release';
    }
}
