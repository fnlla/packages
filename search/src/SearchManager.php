<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Search;

final class SearchManager
{
    public function __construct(private array $config = [])
    {
    }

    public function client(): SearchClientInterface
    {
        $driver = strtolower((string) ($this->config['driver'] ?? 'null'));

        if ($driver === 'meilisearch') {
            $settings = $this->config['meilisearch'] ?? [];
            if (!is_array($settings)) {
                $settings = [];
            }
            $host = (string) ($settings['host'] ?? 'http://127.0.0.1:7700');
            $key = (string) ($settings['key'] ?? '');
            return new MeilisearchHttpClient($host, $key);
        }

        if (isset($this->config['driver_class']) && is_string($this->config['driver_class'])) {
            $class = $this->config['driver_class'];
            if ($class !== '' && class_exists($class)) {
                $driverInstance = new $class($this->config);
                if ($driverInstance instanceof SearchClientInterface) {
                    return $driverInstance;
                }
            }
        }

        return new NullSearchClient();
    }
}
