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

final class AiScaffoldCommand implements CommandInterface
{
    use MakeCommandHelpers;

    public function getName(): string
    {
        return 'ai:scaffold';
    }

    public function getDescription(): string
    {
        return 'Scaffold common app layers (controller, request, policy, model, test).';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $feature = $args[0] ?? '';
        if ($feature === '') {
            $io->error('Feature name is required.');
            return 1;
        }

        $studly = $this->studly($feature);
        $snake = $this->snake($feature);

        $resource = isset($options['resource']);
        $dry = isset($options['dry']);
        $planOnly = $dry || isset($options['plan']);
        $context = (string) ($options['context'] ?? '');
        $all = isset($options['all']);

        $explicit = isset($options['controller']) || isset($options['request']) || isset($options['policy'])
            || isset($options['model']) || isset($options['migration']) || isset($options['test']) || $all;

        $generate = [
            'controller' => $all || isset($options['controller']) || !$explicit,
            'request' => $all || isset($options['request']) || !$explicit,
            'policy' => $all || isset($options['policy']) || !$explicit,
            'model' => $all || isset($options['model']),
            'migration' => $all || isset($options['migration']) || isset($options['model']),
            'test' => $all || isset($options['test']) || !$explicit,
        ];

        $plan = [];
        if ($generate['controller']) {
            $plan[] = ['make:controller', $studly . 'Controller', $resource ? ['resource' => true] : []];
        }
        if ($generate['request']) {
            $plan[] = ['make:request', 'Store' . $studly];
            $plan[] = ['make:request', 'Update' . $studly];
        }
        if ($generate['policy']) {
            $plan[] = ['make:policy', $studly . 'Policy'];
        }
        if ($generate['model']) {
            $plan[] = ['make:model', $studly];
        }
        if ($generate['migration']) {
            $plan[] = ['make:migration', 'create_' . $snake . '_table'];
        }
        if ($generate['test']) {
            $plan[] = ['make:test', $studly . 'ControllerTest'];
        }

        if ($planOnly) {
            $io->line('Scaffold plan:');
            foreach ($plan as $step) {
                $io->line(' - ' . $step[0] . ' ' . $step[1]);
            }
            $this->printSuggestions($io, $snake, $resource, $context);
            return 0;
        }

        $commands = $this->commandMap();
        foreach ($plan as $step) {
            $name = $step[0];
            $target = $step[1];
            $opts = $step[2] ?? [];
            $command = $commands[$name] ?? null;
            if ($command === null) {
                $io->warn('Missing generator for ' . $name);
                continue;
            }
            $status = $command->run([$target], $opts, $io, $root);
            if ($status !== 0) {
                return $status;
            }
        }

        return 0;
    }

    private function printSuggestions(ConsoleIO $io, string $snake, bool $resource, string $context): void
    {
        $prefix = '';
        $context = strtolower(trim($context));
        if ($context === 'api') {
            $prefix = '/api';
        }

        $base = rtrim($prefix . '/' . $snake, '/');

        $io->line('');
        $io->line('Suggested routes:');
        if ($resource) {
            $io->line(' - GET ' . $base);
            $io->line(' - GET ' . $base . '/{id}');
            $io->line(' - POST ' . $base);
            $io->line(' - PUT ' . $base . '/{id}');
            $io->line(' - DELETE ' . $base . '/{id}');
        } else {
            $io->line(' - GET ' . $base);
        }

        $io->line('');
        $io->line('Suggested validation:');
        $io->line(' - required fields, format constraints, and unique rules');
        $io->line(' - idempotency for POST/PUT');
        $io->line('');
    }
}
