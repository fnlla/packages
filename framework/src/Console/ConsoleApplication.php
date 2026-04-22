<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console;

use Fnlla\Console\Commands\MakeCommandCommand;
use Fnlla\Console\Commands\MakeControllerCommand;
use Fnlla\Console\Commands\MakeCrudCommand;
use Fnlla\Console\Commands\MakeBlueprintCommand;
use Fnlla\Console\Commands\MakeJobCommand;
use Fnlla\Console\Commands\MakeListenerCommand;
use Fnlla\Console\Commands\MakeMailCommand;
use Fnlla\Console\Commands\MakeMiddlewareCommand;
use Fnlla\Console\Commands\MakeModelCommand;
use Fnlla\Console\Commands\MakeMigrationCommand;
use Fnlla\Console\Commands\MakeModuleCommand;
use Fnlla\Console\Commands\MakePolicyCommand;
use Fnlla\Console\Commands\MakeRequestCommand;
use Fnlla\Console\Commands\MakeRepositoryCommand;
use Fnlla\Console\Commands\MakeSeederCommand;
use Fnlla\Console\Commands\MakeServiceCommand;
use Fnlla\Console\Commands\MakeTestCommand;
use Fnlla\Console\Commands\MigrateCommand;
use Fnlla\Console\Commands\MigrateRollbackCommand;
use Fnlla\Console\Commands\MigrateStatusCommand;
use Fnlla\Console\Commands\DatabaseBootstrapCommand;
use Fnlla\Console\Commands\AiConfigAdvisorCommand;
use Fnlla\Console\Commands\AiSecurityLintCommand;
use Fnlla\Console\Commands\AiObservabilityCommand;
use Fnlla\Console\Commands\AiDocsSyncCommand;
use Fnlla\Console\Commands\AiScaffoldCommand;
use Fnlla\Console\Commands\AiDoctorCommand;
use Fnlla\Console\Commands\AiTestPlanCommand;
use Fnlla\Console\Commands\AiRoadmapBalanceCommand;
use Fnlla\Console\Commands\AiReleaseNotesCommand;
use Fnlla\Console\Commands\QueueWorkCommand;
use Fnlla\Console\Commands\RoutesCacheCommand;
use Fnlla\Console\Commands\RoutesClearCommand;
use Fnlla\Console\Commands\ScheduleRunCommand;
use Fnlla\Console\Commands\SeedCommand;

final class ConsoleApplication
{
    private string $root;
    private ConsoleIO $io;
    private array $commands = [];

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
        $this->io = new ConsoleIO();
        $this->registerDefaults();
        $this->registerFromConfig();
    }

    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? 'help';
        if ($commandName === 'help' || $commandName === 'list' || $commandName === '--help' || $commandName === '-h') {
            $this->printHelp();
            return 0;
        }

        $command = $this->commands[$commandName] ?? null;
        if (!$command instanceof CommandInterface) {
            $this->io->error("Unknown command: {$commandName}");
            $this->printHelp();
            return 1;
        }

        [$args, $options] = $this->parseArgs(array_slice($argv, 2));
        return $command->run($args, $options, $this->io, $this->root);
    }

    private function registerDefaults(): void
    {
        $this->register(new MakeModuleCommand());
        $this->register(new MakeCrudCommand());
        $this->register(new MakeBlueprintCommand());
        $this->register(new MakeControllerCommand());
        $this->register(new MakeRequestCommand());
        $this->register(new MakePolicyCommand());
        $this->register(new MakeMiddlewareCommand());
        $this->register(new MakeMailCommand());
        $this->register(new MakeModelCommand());
        $this->register(new MakeMigrationCommand());
        $this->register(new MakeSeederCommand());
        $this->register(new MakeServiceCommand());
        $this->register(new MakeRepositoryCommand());
        $this->register(new MakeJobCommand());
        $this->register(new MakeListenerCommand());
        $this->register(new MakeTestCommand());
        $this->register(new MakeCommandCommand());
        $this->register(new AiScaffoldCommand());
        $this->register(new AiDoctorCommand());
        $this->register(new AiConfigAdvisorCommand());
        $this->register(new AiSecurityLintCommand());
        $this->register(new AiObservabilityCommand());
        $this->register(new AiDocsSyncCommand());
        $this->register(new AiTestPlanCommand());
        $this->register(new AiRoadmapBalanceCommand());
        $this->register(new AiReleaseNotesCommand());
        $this->register(new MigrateCommand());
        $this->register(new MigrateStatusCommand());
        $this->register(new MigrateRollbackCommand());
        $this->register(new DatabaseBootstrapCommand());
        $this->register(new SeedCommand());
        $this->register(new QueueWorkCommand());
        $this->register(new RoutesCacheCommand());
        $this->register(new RoutesClearCommand());
        $this->register(new ScheduleRunCommand());
    }

    private function registerFromConfig(): void
    {
        $configFile = $this->resolveConfigFile([
            'config/console/console.php',
            'config/console.php',
        ]);
        if ($configFile === null) {
            return;
        }

        $config = require $configFile;
        if (!is_array($config)) {
            return;
        }

        $list = $config['commands'] ?? $config;
        if (!is_array($list)) {
            return;
        }

        foreach ($list as $commandClass) {
            if (!is_string($commandClass) || $commandClass === '') {
                continue;
            }
            if (!class_exists($commandClass)) {
                continue;
            }
            $instance = new $commandClass();
            if ($instance instanceof CommandInterface) {
                $this->register($instance);
            }
        }
    }

    private function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    /**
     * @param string[] $relativePaths
     */
    private function resolveConfigFile(array $relativePaths): ?string
    {
        foreach ($relativePaths as $relativePath) {
            $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
            $path = $this->root . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function parseArgs(array $argv): array
    {
        $args = [];
        $options = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $trimmed = substr($arg, 2);
                if ($trimmed === '') {
                    continue;
                }
                if (str_contains($trimmed, '=')) {
                    [$key, $value] = explode('=', $trimmed, 2);
                    $options[$key] = $value;
                } else {
                    $options[$trimmed] = true;
                }
                continue;
            }
            if (str_starts_with($arg, '-') && strlen($arg) > 1) {
                $trimmed = substr($arg, 1);
                if ($trimmed === '') {
                    continue;
                }
                if (str_contains($trimmed, '=')) {
                    [$key, $value] = explode('=', $trimmed, 2);
                    $options[$key] = $value;
                } else {
                    foreach (str_split($trimmed) as $flag) {
                        if ($flag !== '') {
                            $options[$flag] = true;
                        }
                    }
                }
                continue;
            }

            $args[] = $arg;
        }

        return [$args, $options];
    }

    private function printHelp(): void
    {
        $this->io->line('Fnlla CLI');
        $this->io->line('');
        $this->io->line('Commands:');

        ksort($this->commands);
        foreach ($this->commands as $command) {
            $this->io->line('  ' . $command->getName() . ' - ' . $command->getDescription());
        }

        $this->io->line('');
        $this->io->line('Use: Fnlla <command> [args] [--options]');
    }
}
