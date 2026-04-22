<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Database\ConnectionManager;
use Fnlla\Database\MigrationRunner;
use Fnlla\Core\ConfigRepository;

trait DatabaseCommandTrait
{
    private function makeRunner(string $root, ?string $pathOverride = null): MigrationRunner
    {
        if (getenv('APP_ROOT') === false) {
            putenv('APP_ROOT=' . $root);
        }

        $config = [];
        $configFile = $this->resolveDatabaseConfigFile($root);
        if ($configFile !== null) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $config = $loaded;
            }
        }

        if ($config === []) {
            $repo = ConfigRepository::fromRoot($root);
            $config = $repo->get('database', []);
            if (!is_array($config)) {
                $config = [];
            }
        }

        $manager = new ConnectionManager($config);
        return new MigrationRunner($manager, $pathOverride);
    }

    private function resolveDatabaseConfigFile(string $root): ?string
    {
        $candidates = [
            'config/database/database.php',
            'config/database.php',
        ];

        foreach ($candidates as $relativePath) {
            $relativePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relativePath);
            $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
