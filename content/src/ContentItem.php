<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Content;

final class ContentItem
{
    public function __construct(
        private string $slug,
        private array $meta,
        private string $body,
        private string $path
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
}
