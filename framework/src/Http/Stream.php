<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Http;

use InvalidArgumentException;
use Fnlla\Support\Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;
    private ?int $size = null;
    private bool $seekable = false;
    private bool $readable = false;
    private bool $writable = false;
    private array $metadata = [];
    /** @var callable|null */
    private $callback = null;

    /**
     * @param resource $resource
     */
    public function __construct(mixed $resource)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Stream resource must be a valid resource.');
        }

        $this->resource = $resource;
        $this->metadata = stream_get_meta_data($resource);
        $this->seekable = (bool) $this->metadata['seekable'];
        $mode = (string) $this->metadata['mode'];
        $this->readable = strpbrk($mode, 'r+') !== false;
        $this->writable = strpbrk($mode, 'waxc+') !== false;
    }

    public static function fromString(string $content = ''): self
    {
        $resource = fopen('php://temp', 'r+');
        if ($resource === false) {
            throw new RuntimeException('Unable to create stream.');
        }
        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }
        return new self($resource);
    }

    public function withCallback(callable $callback): self
    {
        $clone = clone $this;
        $clone->callback = $callback;
        return $clone;
    }

    public function hasCallback(): bool
    {
        return is_callable($this->callback);
    }

    public function invokeCallback(): void
    {
        if (is_callable($this->callback)) {
            ($this->callback)();
        }
    }

    public function __toString(): string
    {
        if (!$this->resource) {
            return '';
        }
        try {
            $this->rewind();
            return $this->getContents();
        } catch (Throwable $e) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource) {
            fclose($this->resource);
        }
        $this->resource = null;
    }

    /**
     * @return resource|null
     */
    public function detach(): mixed
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->size = null;
        $this->seekable = false;
        $this->readable = false;
        $this->writable = false;
        $this->metadata = [];
        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }
        if (!$this->resource) {
            return null;
        }
        $stats = fstat($this->resource);
        if ($stats === false || !isset($stats['size'])) {
            return null;
        }
        $this->size = (int) $stats['size'];
        return $this->size;
    }

    public function tell(): int
    {
        if (!$this->resource) {
            throw new RuntimeException('No stream available.');
        }
        $pos = ftell($this->resource);
        if ($pos === false) {
            throw new RuntimeException('Unable to get stream position.');
        }
        return $pos;
    }

    public function eof(): bool
    {
        return !$this->resource || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->resource || !$this->seekable) {
            throw new RuntimeException('Stream is not seekable.');
        }
        if (fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek stream.');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write(string $string): int
    {
        if (!$this->resource || !$this->writable) {
            throw new RuntimeException('Stream is not writable.');
        }
        $this->size = null;
        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream.');
        }
        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function read(int $length): string
    {
        if (!$this->resource || !$this->readable) {
            throw new RuntimeException('Stream is not readable.');
        }
        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream.');
        }
        return $result;
    }

    public function getContents(): string
    {
        if (!$this->resource) {
            return '';
        }
        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents.');
        }
        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }
        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }
        return $meta[$key] ?? null;
    }
}






