<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Settings;

final class SettingsStore
{
    /** @var array<string, string>|null */
    private ?array $cache = null;
    private ?bool $ready = null;

    public function __construct(private SettingsRepository $repository)
    {
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->repository->all();
        }

        return $this->cache;
    }

    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }

        $this->ready = $this->repository->tableExists();
        return $this->ready;
    }

    public function get(string $key, string $default = ''): string
    {
        $settings = $this->all();
        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        $value = (string) $settings[$key];
        return $value !== '' ? $value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $this->repository->set($key, $value);
        $this->cache = null;
    }

    /**
     * @param array<string, string|null> $values
     */
    public function setMany(array $values): void
    {
        $this->repository->setMany($values);
        $this->cache = null;
    }

    public function delete(string $key): void
    {
        $this->repository->delete($key);
        $this->cache = null;
    }

    public function clear(): void
    {
        $this->cache = null;
        $this->ready = null;
    }
}
