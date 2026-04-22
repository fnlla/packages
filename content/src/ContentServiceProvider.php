<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Content;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class ContentServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $config = $app->has(ConfigRepository::class) ? $app->make(ConfigRepository::class) : null;
        $root = method_exists($app, 'basePath') ? (string) $app->basePath() : ConfigRepository::resolveAppRoot();
        $path = $config instanceof ConfigRepository ? $config->get('content.path', 'content') : 'content';
        if (!is_string($path) || $path === '') {
            $path = 'content';
        }
        if (!str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:\\\\/', $path)) {
            $path = rtrim($root, '/\\') . '/' . $path;
        }

        $app->singleton(ContentRepository::class, fn (): ContentRepository => new ContentRepository($path));
    }
}
