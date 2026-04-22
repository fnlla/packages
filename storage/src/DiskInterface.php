<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Storage;

interface DiskInterface
{
    public function path(string $path): string;

    public function put(string $path, string $contents): bool;

    public function putFile(string $path, string $sourcePath): bool;

    public function get(string $path): ?string;

    public function exists(string $path): bool;

    public function delete(string $path): bool;

    public function url(string $path): string;
}
