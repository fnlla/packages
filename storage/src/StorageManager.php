<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Storage;

final class StorageManager
{
    public function __construct(private array $config = [])
    {
    }

    public function disk(?string $name = null): DiskInterface
    {
        $name = $name ?: (string) ($this->config['default'] ?? 'local');
        $disks = $this->config['disks'] ?? [];
        if (!is_array($disks)) {
            $disks = [];
        }
        $disk = $disks[$name] ?? $disks['local'] ?? [];
        if (!is_array($disk)) {
            $disk = [];
        }

        $driverClass = (string) ($disk['driver_class'] ?? '');
        if ($driverClass !== '' && class_exists($driverClass)) {
            return $this->buildDriver($driverClass, $disk);
        }

        $driver = strtolower((string) ($disk['driver'] ?? 'local'));
        if ($driver === 'local') {
            $root = (string) ($disk['root'] ?? 'storage/uploads');
            $url = (string) ($disk['url'] ?? '/uploads');
            return new LocalDisk($root, $url);
        }

        if ($driver === 's3' && class_exists(\Fnlla\StorageS3\S3Disk::class)) {
            return new \Fnlla\StorageS3\S3Disk($disk);
        }

        if ($driver !== '' && class_exists($driver)) {
            return $this->buildDriver($driver, $disk);
        }

        throw new \RuntimeException('Storage driver not available: ' . $driver);
    }

    private function buildDriver(string $driverClass, array $config): DiskInterface
    {
        $driver = new $driverClass($config);
        if (!$driver instanceof DiskInterface) {
            throw new \RuntimeException('Storage driver must implement DiskInterface: ' . $driverClass);
        }
        return $driver;
    }
}
