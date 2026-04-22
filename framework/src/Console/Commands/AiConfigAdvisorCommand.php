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

final class AiConfigAdvisorCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'ai:config-advisor';
    }

    public function getDescription(): string
    {
        return 'Suggest smart defaults for cache, queue, rate-limit, and session settings.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $config = ConfigRepository::fromRoot($root);
        $appEnv = (string) $config->get('app.env', 'local');

        $cache = $config->get('cache', []);
        $queue = $config->get('queue', []);
        $http = $config->get('http', []);
        $session = $config->get('session', []);

        $cacheDriver = is_array($cache) ? (string) ($cache['driver'] ?? 'file') : 'file';
        $cacheTtl = is_array($cache) ? (int) ($cache['ttl'] ?? 3600) : 3600;
        $queueDriver = is_array($queue) ? (string) ($queue['driver'] ?? 'sync') : 'sync';
        $rate = $this->extractRateLimit($http);

        $recommendations = [];
        $suggestedEnv = [];

        $redisConfigured = $this->redisConfigured($cache);

        if ($appEnv === 'prod' && $cacheDriver === 'file') {
            $recommendations[] = 'Cache: file driver in production. Consider Redis for better concurrency and speed.';
            $suggestedEnv[] = 'CACHE_DRIVER=redis';
        }

        if ($cacheTtl < 300) {
            $recommendations[] = 'Cache: TTL is very low. Consider CACHE_TTL=3600 for general app caching.';
            $suggestedEnv[] = 'CACHE_TTL=3600';
        }

        if ($appEnv === 'prod' && $queueDriver === 'sync') {
            $recommendations[] = 'Queue: sync driver in production. Use database or Redis for background jobs.';
            $suggestedEnv[] = $redisConfigured ? 'QUEUE_DRIVER=redis' : 'QUEUE_DRIVER=database';
        }

        if ($appEnv === 'prod' && !$rate['enabled']) {
            $recommendations[] = 'Rate limit: disabled in production. Enable a basic per-IP limit.';
            $suggestedEnv[] = 'RATE_LIMIT_ENABLED=1';
            $suggestedEnv[] = 'RATE_LIMIT_MAX=120';
            $suggestedEnv[] = 'RATE_LIMIT_MINUTES=1';
            $suggestedEnv[] = 'RATE_LIMIT_KEY=ip';
        }

        if ($appEnv === 'prod' && !$this->sessionSecure($session)) {
            $recommendations[] = 'Session: secure cookies should be enabled in production.';
            $suggestedEnv[] = 'SESSION_SECURE=1';
        }

        if ($recommendations === []) {
            $recommendations[] = 'No changes suggested. Current defaults look healthy.';
        }

        $io->line('Config Advisor');
        $io->line('Env: ' . $appEnv);
        $io->line('Cache: ' . $cacheDriver . ' (ttl ' . $cacheTtl . 's)');
        $io->line('Queue: ' . $queueDriver);
        $io->line('Rate limit: ' . ($rate['enabled'] ? $rate['label'] : 'disabled'));
        $io->line('');

        $io->line('Recommendations:');
        foreach ($recommendations as $item) {
            $io->line(' - ' . $item);
        }

        if ($suggestedEnv !== []) {
            $io->line('');
            $io->line('Suggested .env overrides:');
            foreach (array_values(array_unique($suggestedEnv)) as $line) {
                $io->line(' - ' . $line);
            }
        }

        return 0;
    }

    private function sessionSecure(mixed $session): bool
    {
        if (!is_array($session)) {
            return false;
        }
        $cookie = $session['cookie'] ?? [];
        if (!is_array($cookie)) {
            return false;
        }
        return (bool) ($cookie['secure'] ?? false);
    }

    /**
     * @return array{enabled: bool, max: int, minutes: int, key: string, label: string}
     */
    private function extractRateLimit(mixed $http): array
    {
        $enabled = false;
        $max = 0;
        $minutes = 0;
        $key = '';
        $label = '';

        if (is_array($http)) {
            $groups = $http['middleware_groups'] ?? [];
            if (is_array($groups) && isset($groups['web']) && is_array($groups['web'])) {
                foreach ($groups['web'] as $item) {
                    if (!is_string($item)) {
                        continue;
                    }
                    if (str_starts_with($item, 'rate:')) {
                        $enabled = true;
                        $spec = substr($item, 5);
                        $parts = array_map('trim', explode(',', $spec));
                        $max = isset($parts[0]) ? (int) $parts[0] : 0;
                        $minutes = isset($parts[1]) ? (int) $parts[1] : 0;
                        $key = isset($parts[2]) ? (string) $parts[2] : '';
                        $label = $max . '/' . ($minutes > 0 ? $minutes : 1) . ' ' . ($key !== '' ? $key : 'ip');
                        break;
                    }
                }
            }
        }

        return [
            'enabled' => $enabled,
            'max' => $max,
            'minutes' => $minutes,
            'key' => $key,
            'label' => $label,
        ];
    }

    private function redisConfigured(mixed $cache): bool
    {
        if (!is_array($cache)) {
            return false;
        }
        $redis = $cache['redis'] ?? [];
        if (!is_array($redis)) {
            return false;
        }
        $url = trim((string) ($redis['url'] ?? ''));
        $host = trim((string) ($redis['host'] ?? ''));
        return $url !== '' || $host !== '';
    }
}
