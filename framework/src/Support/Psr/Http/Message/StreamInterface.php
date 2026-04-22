<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Support\Psr\Http\Message;

interface StreamInterface
{
    public function __toString(): string;

    public function close(): void;

    /**
     * @return resource|null
     */
    public function detach(): mixed;

    public function getSize(): ?int;

    public function tell(): int;

    public function eof(): bool;

    public function isSeekable(): bool;

    public function seek(int $offset, int $whence = SEEK_SET): void;

    public function rewind(): void;

    public function isWritable(): bool;

    public function write(string $string): int;

    public function isReadable(): bool;

    public function read(int $length): string;

    public function getContents(): string;

    public function getMetadata(?string $key = null): mixed;
}




