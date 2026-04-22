<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Factory;

use Fnlla\Support\Psr\Http\Message\StreamInterface;

interface StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface;

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface;

    /**
     * @param resource $resource
     */
    public function createStreamFromResource(mixed $resource): StreamInterface;
}






