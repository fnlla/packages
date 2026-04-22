<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Cache;

final class TaggedCache
{
    /**
     * @var array<int, string>
     */
    private array $tags;

    /**
     * @param array<int, string> $tags
     */
    public function __construct(private CacheManager $manager, array $tags)
    {
        $this->tags = array_values(array_filter(array_map('strval', $tags), fn ($item) => $item !== ''));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->get($this->taggedKey($key), $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->manager->set($this->taggedKey($key), $value, $ttl);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->manager->remember($this->taggedKey($key), $ttl, $callback);
    }

    public function delete(string $key): bool
    {
        return $this->manager->delete($this->taggedKey($key));
    }

    public function invalidateTag(string $tag): bool
    {
        return $this->manager->invalidateTag($tag);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    private function taggedKey(string $key): string
    {
        return $this->manager->taggedKey($this->tags, $key);
    }
}
