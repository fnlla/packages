<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Session;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class SessionServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(FileSessionStore::class, function () use ($app): FileSessionStore {
            $config = $app->config()->get('session', []);
            if (!is_array($config)) {
                $config = [];
            }

            $ttl = (int) ($config['ttl'] ?? 7200);
            $path = (string) ($config['path'] ?? '');
            if ($path === '') {
                if (method_exists($app, 'basePath')) {
                    $path = rtrim((string) $app->basePath(), '/\\') . '/storage/sessions';
                } else {
                    $path = getcwd() . '/storage/sessions';
                }
            }

            $cookie = is_array($config['cookie'] ?? null) ? $config['cookie'] : [];
            $lockFiles = array_key_exists('lock', $config) ? (bool) $config['lock'] : true;
            $gcProbability = (int) ($config['gc_probability'] ?? 1);

            return new FileSessionStore($path, $ttl, $cookie, $lockFiles, $gcProbability);
        });

        $app->singleton(SessionInterface::class, fn () => $app->make(FileSessionStore::class));
    }
}
