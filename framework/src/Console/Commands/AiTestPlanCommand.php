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

final class AiTestPlanCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:test-plan';
    }

    public function getDescription(): string
    {
        return 'Generate a deterministic test plan checklist for a feature/module.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $feature = trim($args[0] ?? '');
        if ($feature === '') {
            $io->error('Feature name is required. Example: ai:test-plan Checkout');
            return 1;
        }

        $slug = $this->slug($feature);
        $output = (string) ($options['output'] ?? $options['out'] ?? ($root . '/storage/ai/test-plans/' . $slug . '.md'));
        $dry = isset($options['dry']) || isset($options['n']);

        $plan = $this->buildPlan($feature);

        if ($dry) {
            $io->line($plan);
            return 0;
        }

        $dir = dirname($output);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $io->error('Unable to create output directory: ' . $dir);
            return 1;
        }

        if (@file_put_contents($output, $plan) === false) {
            $io->error('Unable to write plan: ' . $output);
            return 1;
        }

        $io->line('Test plan written to ' . $output);
        return 0;
    }

    private function buildPlan(string $feature): string
    {
        $title = 'AI Test Plan: ' . $feature;
        $lines = [
            '# ' . $title,
            '',
            '## Scope',
            '- Feature: **' . $feature . '**',
            '- Owner: ___',
            '- Target release: ___',
            '',
            '## Functional coverage',
            '- Happy path flow',
            '- Edge cases and error handling',
            '- API + UI parity (if applicable)',
            '',
            '## Validation & data integrity',
            '- Required fields and formats',
            '- Boundary values (min/max)',
            '- Idempotency / duplicate submissions',
            '',
            '## Security',
            '- AuthZ checks (role/ownership)',
            '- CSRF for web routes',
            '- Rate limits for public endpoints',
            '',
            '## Performance',
            '- Query count and N+1 checks',
            '- Worst-case payload size',
            '- Cache opportunities',
            '',
            '## Observability',
            '- Log key events and failures',
            '- Trace IDs preserved',
            '- Alert on critical errors',
            '',
            '## Smoke tests (minimum)',
            '- `GET /health` + `GET /ready`',
            '- Core endpoint returns 2xx',
            '',
        ];

        $hints = $this->featureHints($feature);
        if ($hints !== []) {
            $lines[] = '## Feature-specific checks';
            foreach ($hints as $hint) {
                $lines[] = '- ' . $hint;
            }
            $lines[] = '';
        }

        $lines[] = '## Notes';
        $lines[] = '- Add integration tests for external services.';
        $lines[] = '- Use feature flags when rolling out risky changes.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function featureHints(string $feature): array
    {
        $feature = strtolower($feature);
        $hints = [];

        if (str_contains($feature, 'auth') || str_contains($feature, 'login') || str_contains($feature, 'password')) {
            $hints[] = 'Login/logout flow (session, remember token, reset).';
            $hints[] = 'Brute force/rate-limit checks.';
        }
        if (str_contains($feature, 'payment') || str_contains($feature, 'billing') || str_contains($feature, 'checkout')) {
            $hints[] = 'Payment provider webhook handling.';
            $hints[] = 'Idempotent payment confirmation.';
        }
        if (str_contains($feature, 'upload') || str_contains($feature, 'file')) {
            $hints[] = 'File size/type validation.';
            $hints[] = 'Storage permissions and cleanup.';
        }
        if (str_contains($feature, 'api')) {
            $hints[] = 'API contract + error shape stability.';
            $hints[] = 'Auth header/token tests.';
        }

        return $hints;
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : 'feature';
    }
}
