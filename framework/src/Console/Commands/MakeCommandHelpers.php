<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

trait MakeCommandHelpers
{
    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    protected function snake(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value) ?? $value;
        $value = strtolower(str_replace(['-', ' '], '_', $value));
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    /**
     * @return array<string, \Fnlla\Console\CommandInterface>
     */
    protected function commandMap(): array
    {
        return [
            'make:controller' => new MakeControllerCommand(),
            'make:request' => new MakeRequestCommand(),
            'make:policy' => new MakePolicyCommand(),
            'make:model' => new MakeModelCommand(),
            'make:migration' => new MakeMigrationCommand(),
            'make:test' => new MakeTestCommand(),
        ];
    }
}
