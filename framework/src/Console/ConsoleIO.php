<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console;

final class ConsoleIO
{
    public function line(string $message = ''): void
    {
        fwrite(STDOUT, $message . "\n");
    }

    public function info(string $message): void
    {
        $this->line($message);
    }

    public function warn(string $message): void
    {
        fwrite(STDERR, "WARN: {$message}\n");
    }

    public function error(string $message): void
    {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
}
