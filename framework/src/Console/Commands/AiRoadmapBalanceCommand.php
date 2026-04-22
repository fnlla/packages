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

final class AiRoadmapBalanceCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:roadmap-balance';
    }

    public function getDescription(): string
    {
        return 'Suggest milestone ordering and ownership balance from a roadmap input.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $input = (string) ($options['input'] ?? $options['in'] ?? ($root . '/storage/ai/roadmap-input.json'));
        $output = (string) ($options['output'] ?? $options['out'] ?? ($root . '/storage/ai/roadmap-balance.md'));
        $dry = isset($options['dry']) || isset($options['n']);

        $data = $this->readInput($input);
        $report = $this->buildReport($data, $input);

        if ($dry) {
            $io->line($report);
            return 0;
        }

        $dir = dirname($output);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $io->error('Unable to create output directory: ' . $dir);
            return 1;
        }

        if (@file_put_contents($output, $report) === false) {
            $io->error('Unable to write report: ' . $output);
            return 1;
        }

        $io->line('Roadmap balance report written to ' . $output);
        return 0;
    }

    private function readInput(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return [];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    private function buildReport(array $data, string $inputPath): string
    {
        $lines = [
            '# AI Roadmap Balance',
            '',
        ];

        $milestones = $data['milestones'] ?? [];
        if (!is_array($milestones) || $milestones === []) {
            $lines[] = 'No roadmap input found. Provide a JSON file to drive the balance output:';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = '{';
            $lines[] = '  "milestones": [';
            $lines[] = '    { "name": "MVP onboarding", "risk": "high", "effort": 3, "owner": "team-a" },';
            $lines[] = '    { "name": "Payments v1", "risk": "medium", "effort": 5, "owner": "team-b" }';
            $lines[] = '  ]';
            $lines[] = '}';
            $lines[] = '```';
            $lines[] = '';
            $lines[] = 'Place the file at: `' . $inputPath . '`.';
            $lines[] = '';
            $lines[] = '## Default suggestions';
            $lines[] = '- Prioritise high-risk items early to reduce late surprises.';
            $lines[] = '- Split big milestones into 2-3 smaller increments.';
            $lines[] = '- Balance ownership to avoid one team holding all critical paths.';
            $lines[] = '';

            return implode("\n", $lines);
        }

        $scored = [];
        foreach ($milestones as $milestone) {
            if (!is_array($milestone)) {
                continue;
            }
            $name = (string) ($milestone['name'] ?? 'Milestone');
            $risk = $this->riskScore((string) ($milestone['risk'] ?? 'medium'));
            $effort = (int) ($milestone['effort'] ?? 3);
            $owner = (string) ($milestone['owner'] ?? '');
            $score = ($risk * 10) - $effort;
            $scored[] = [
                'name' => $name,
                'risk' => $risk,
                'effort' => $effort,
                'owner' => $owner,
                'score' => $score,
            ];
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $lines[] = '## Suggested order';
        $index = 1;
        foreach ($scored as $item) {
            $owner = $item['owner'] !== '' ? ' (owner: ' . $item['owner'] . ')' : '';
            $lines[] = $index . '. ' . $item['name'] . ' � risk ' . $this->riskLabel($item['risk']) . ', effort ' . $item['effort'] . $owner;
            $index++;
        }
        $lines[] = '';

        $lines[] = '## Ownership balance';
        $ownerCounts = [];
        foreach ($scored as $item) {
            $owner = $item['owner'] !== '' ? $item['owner'] : 'unassigned';
            $ownerCounts[$owner] = ($ownerCounts[$owner] ?? 0) + 1;
        }
        arsort($ownerCounts);
        foreach ($ownerCounts as $owner => $count) {
            $lines[] = '- ' . $owner . ': ' . $count . ' milestone(s)';
        }
        $lines[] = '';

        $lines[] = '## Recommendations';
        $lines[] = '- Move high-risk items into earlier milestones.';
        $lines[] = '- Split milestones with effort > 5 into smaller deliverables.';
        $lines[] = '- Rebalance ownership if one team has >50% of critical items.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function riskScore(string $risk): int
    {
        $risk = strtolower(trim($risk));
        return match ($risk) {
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            default => 3,
        };
    }

    private function riskLabel(int $score): string
    {
        return match ($score) {
            5 => 'critical',
            4 => 'high',
            3 => 'medium',
            2 => 'low',
            default => 'medium',
        };
    }
}
