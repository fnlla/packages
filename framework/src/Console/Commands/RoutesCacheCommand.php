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
use Fnlla\Http\RouteCacheCompiler;
use RuntimeException;

final class RoutesCacheCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'routes:cache';
    }

    public function getDescription(): string
    {
        return 'Compile and cache routes.';
    }

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int
    {
        $path = $options['path'] ?? null;
        $path = is_string($path) && $path !== '' ? $path : null;

        try {
            $compiler = new RouteCacheCompiler();
            $cachePath = $compiler->compile($root, null, $path);
            $payload = is_file($cachePath) ? require $cachePath : null;
            if (is_array($payload) && isset($payload['__Fnlla_routes_cache']) && is_array($payload['__Fnlla_routes_cache'])) {
                $meta = $payload['__Fnlla_routes_cache'];
                if (!empty($meta['disabled'])) {
                    $issues = isset($meta['issues']) && is_array($meta['issues']) ? $meta['issues'] : [];
                    $hasNonCacheable = false;
                    foreach ($issues as $issue) {
                        if (!is_string($issue)) {
                            continue;
                        }
                        if (str_contains($issue, 'handler is not cacheable') || str_contains($issue, 'middleware is not cacheable')) {
                            $hasNonCacheable = true;
                            break;
                        }
                    }
                    if ($hasNonCacheable) {
                        $io->line('Routes cache disabled: detected non-cacheable handlers/middleware (likely closures).');
                    }
                    $reason = isset($meta['reason']) && is_string($meta['reason']) ? $meta['reason'] : 'unknown reason';
                    $io->line('Routes cache disabled: ' . $reason);
                    return 0;
                }
            }

            $io->line('Routes cached: ' . $cachePath);
            return 0;
        } catch (RuntimeException $e) {
            $io->error($e->getMessage());
            return 1;
        }
    }
}
