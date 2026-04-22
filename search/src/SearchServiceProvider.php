<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

use Fnlla\Core\Container;
use Fnlla\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(Container $app): void
    {
        $app->singleton(SearchManager::class, function () use ($app): SearchManager {
            $config = $app->config()->get('search', []);
            if (!is_array($config)) {
                $config = [];
            }
            return new SearchManager($config);
        });

        $app->singleton(SearchClientInterface::class, function () use ($app): SearchClientInterface {
            $manager = $app->make(SearchManager::class);
            return $manager instanceof SearchManager ? $manager->client() : new NullSearchClient();
        });
    }
}
