<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

final class ProviderReport
{
    private array $entries = [];

    public function addEntry(
        string $provider,
        string $source,
        bool $enabled,
        int $priority = 0,
        array $capabilities = [],
        array $validationIssues = []
    ): void {
        $this->entries[$provider] = [
            'provider' => $provider,
            'source' => $source,
            'enabled' => $enabled,
            'priority' => $priority,
            'capabilities' => array_values($capabilities),
            'validationIssues' => array_values($validationIssues),
        ];
    }

    public function toArray(): array
    {
        return array_values($this->entries);
    }

    public function toText(): string
    {
        if ($this->entries === []) {
            return "Provider report: (empty)\n";
        }

        $lines = ["Provider report:"];
        foreach ($this->entries as $entry) {
            $lines[] = '- ' . $entry['provider']
                . ' | source=' . $entry['source']
                . ' | enabled=' . ($entry['enabled'] ? 'yes' : 'no')
                . ' | priority=' . (string) $entry['priority'];
            if (!empty($entry['capabilities'])) {
                $lines[] = '  capabilities: ' . implode(', ', $entry['capabilities']);
            }
            if (!empty($entry['validationIssues'])) {
                $lines[] = '  issues: ' . implode('; ', $entry['validationIssues']);
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
