<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param array<int, string> $args
     * @param array<string, mixed> $options
     */
    public function run(array $args, array $options, ConsoleIO $io, string $root): int;
}
