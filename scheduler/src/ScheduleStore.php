<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Scheduler;

use RuntimeException;

final class ScheduleStore
{
    public function __construct(private string $path)
    {
    }

    public function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        if ($contents === false || $contents === '') {
            return [];
        }

        $data = json_decode($contents, true);
        return is_array($data) ? $data : [];
    }

    public function save(array $data): void
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create schedule cache directory: ' . $dir);
        }

        $encoded = json_encode($data, JSON_PRETTY_PRINT);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode schedule cache data.');
        }

        file_put_contents($this->path, $encoded);
    }
}
