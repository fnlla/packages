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

final class AiDoctorCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:doctor';
    }

    public function getDescription(): string
    {
        return 'Run an AI readiness check and suggest safe defaults.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $config = ConfigRepository::fromRoot($root);

        $aiConfigExists = $this->configFileExists($root, [
            'config/ai/ai.php',
            'config/ai.php',
        ]);
        $aiRagExists = $this->configFileExists($root, [
            'config/ai/rag.php',
            'config/ai_rag.php',
        ]);

        $driver = (string) $config->get('ai.driver', env('AI_DRIVER', 'openai'));
        $provider = (string) $config->get('ai.provider', env('AI_PROVIDER', 'openai'));
        $apiKey = (string) $config->get('ai.api_key', env('OPENAI_API_KEY', ''));
        $model = (string) $config->get('ai.model', env('OPENAI_MODEL', ''));
        $embedding = (string) $config->get('ai.embedding_model', env('OPENAI_EMBEDDING_MODEL', ''));

        $ragEnabled = (bool) $config->get('ai_rag.enabled', env('AI_RAG_ENABLED', false));

        $policyEnabled = (bool) $config->get('ai_policy.enabled', env('AI_POLICY_ENABLED', true));
        $redactionEnabled = (bool) $config->get('ai_redaction.enabled', env('AI_REDACTION_ENABLED', true));
        $telemetryEnabled = (bool) $config->get('ai_telemetry.enabled', env('AI_TELEMETRY_ENABLED', false));
        $actionsEnabled = (bool) $config->get('ai_actions.enabled', env('AI_ACTIONS_ENABLED', false));
        $routerEnabled = (bool) $config->get('ai_router.enabled', env('AI_ROUTER_ENABLED', false));

        $issues = [];
        $tips = [];

        if (!$aiConfigExists) {
            $issues[] = 'Missing config/ai/ai.php.';
            $tips[] = 'Copy the AI config from documentation/src/ai-integrations.md or app/config/ai/ai.php.';
        }

        if ($driver === 'openai' && $apiKey === '') {
            $issues[] = 'OpenAI driver selected but OPENAI_API_KEY is missing.';
            $tips[] = 'Set OPENAI_API_KEY in local .env or switch to AI_DRIVER=mock for a mock demo.';
        }

        if ($ragEnabled && !$aiRagExists) {
            $issues[] = 'AI_RAG_ENABLED is on but config/ai/rag.php is missing.';
        }

        if ($actionsEnabled && !$ragEnabled) {
            $tips[] = 'AI actions are safer with AI_RAG_ENABLED=1.';
        }

        if (!$policyEnabled) {
            $tips[] = 'Enable AI_POLICY_ENABLED=1 to keep outputs consistent.';
        }

        if (!$redactionEnabled) {
            $tips[] = 'Enable AI_REDACTION_ENABLED=1 to mask sensitive data.';
        }

        if (!$telemetryEnabled) {
            $tips[] = 'Enable AI_TELEMETRY_ENABLED=1 to audit AI runs.';
        }

        $io->line('AI Doctor');
        $io->line('');
        $io->line('Core:');
        $io->line(' - config/ai/ai.php: ' . ($aiConfigExists ? 'ok' : 'missing'));
        $io->line(' - config/ai/rag.php: ' . ($aiRagExists ? 'ok' : 'missing'));
        $io->line(' - driver: ' . ($driver !== '' ? $driver : 'not set'));
        $io->line(' - provider: ' . ($provider !== '' ? $provider : 'not set'));
        $io->line(' - api key: ' . ($apiKey !== '' ? 'present' : 'missing'));
        $io->line(' - model: ' . ($model !== '' ? $model : 'not set'));
        $io->line(' - embeddings: ' . ($embedding !== '' ? $embedding : 'not set'));
        $io->line('');

        $io->line('RAG:');
        $io->line(' - enabled: ' . ($ragEnabled ? 'yes' : 'no'));
        $io->line('');

        $io->line('Safety + governance:');
        $io->line(' - policy: ' . ($policyEnabled ? 'on' : 'off'));
        $io->line(' - redaction: ' . ($redactionEnabled ? 'on' : 'off'));
        $io->line(' - telemetry: ' . ($telemetryEnabled ? 'on' : 'off'));
        $io->line(' - router: ' . ($routerEnabled ? 'on' : 'off'));
        $io->line('');

        if ($issues === []) {
            $io->line('Status: OK');
        } else {
            $io->line('Status: attention needed');
            foreach ($issues as $issue) {
                $io->line(' - ' . $issue);
            }
        }

        if ($tips !== []) {
            $io->line('');
            $io->line('Suggestions:');
            foreach ($tips as $tip) {
                $io->line(' - ' . $tip);
            }
        }

        return $issues === [] ? 0 : 1;
    }

    /**
     * @param string[] $relativePaths
     */
    private function configFileExists(string $root, array $relativePaths): bool
    {
        foreach ($relativePaths as $relativePath) {
            $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
            if (is_file($root . DIRECTORY_SEPARATOR . $relativePath)) {
                return true;
            }
        }

        return false;
    }
}
