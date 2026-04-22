<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Skills;

final class AiSkillRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $skills;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $skills = $config['skills'] ?? $config;
        $this->skills = is_array($skills) ? $skills : [];
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_values(array_filter(array_keys($this->skills), static fn (string $name): bool => $name !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }
        $skill = $this->skills[$name] ?? [];
        return is_array($skill) ? $skill : [];
    }

    public function instructions(string $name): string
    {
        $skill = $this->get($name);
        $instructions = (string) ($skill['instructions'] ?? '');
        return trim($instructions);
    }
}


