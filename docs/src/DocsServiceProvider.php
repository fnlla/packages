<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Docs;

use Fnlla\Core\ConfigRepository;
use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class DocsServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(DocsPaths::class, function () use ($app): DocsPaths {
            $root = method_exists($app, 'basePath') ? (string) $app->basePath() : ConfigRepository::resolveAppRoot();
            return new DocsPaths($app->config(), $root);
        });

        $app->singleton(DocsFinder::class, function () use ($app): DocsFinder {
            $paths = $app->make(DocsPaths::class);
            return new DocsFinder($paths->all());
        });

        $app->singleton(DocsManager::class, function () use ($app): DocsManager {
            $paths = $app->make(DocsPaths::class);
            return new DocsManager($app->config(), $paths);
        });

        $app->singleton(DocsMarkdownParser::class, fn (): DocsMarkdownParser => new DocsMarkdownParser());
        $app->singleton(DocsMarkdownRenderer::class, function () use ($app): DocsMarkdownRenderer {
            $parser = $app->make(DocsMarkdownParser::class);
            return new DocsMarkdownRenderer($parser instanceof DocsMarkdownParser ? $parser : new DocsMarkdownParser());
        });
    }
}


