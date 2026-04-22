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
use Fnlla\Support\Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Uploaded file wrapper with safe storage helpers.
 *
 * @api
 */
final class UploadedFile implements UploadedFileInterface
{
    private ?StreamInterface $stream = null;
    private ?string $clientFilename = null;
    private ?string $clientMediaType = null;
    private ?int $size = null;
    private int $error;
    private ?string $tmpName = null;

    public function __construct(array $file, ?StreamInterface $stream = null)
    {
        $this->error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $this->size = isset($file['size']) ? (int) $file['size'] : null;
        $this->clientFilename = isset($file['name']) ? (string) $file['name'] : null;
        $this->clientMediaType = isset($file['type']) ? (string) $file['type'] : null;
        $this->tmpName = isset($file['tmp_name']) ? (string) $file['tmp_name'] : null;
        if ($stream instanceof StreamInterface) {
            $this->stream = $stream;
        }
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    public function getStream(): StreamInterface
    {
        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        if ($this->tmpName === null || $this->tmpName === '' || !is_file($this->tmpName)) {
            throw new RuntimeException('No stream available for upload.');
        }

        $resource = fopen($this->tmpName, 'r');
        if ($resource === false) {
            throw new RuntimeException('Unable to open upload stream.');
        }

        $this->stream = new Stream($resource);
        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path is empty.');
        }

        if (!$this->isValid()) {
            throw new RuntimeException('Upload failed with error code ' . $this->error . '.');
        }

        \Fnlla\Support\safe_mkdir(dirname($targetPath), 0755, true, 'uploads');

        if ($this->tmpName !== null && is_uploaded_file($this->tmpName)) {
            if (!move_uploaded_file($this->tmpName, $targetPath)) {
                throw new RuntimeException('Unable to move uploaded file.');
            }
            return;
        }

        $stream = $this->getStream();
        $destination = fopen($targetPath, 'w');
        if ($destination === false) {
            throw new RuntimeException('Unable to write uploaded file.');
        }
        $source = $stream->detach();
        if (!is_resource($source)) {
            fclose($destination);
            throw new RuntimeException('Upload stream not available.');
        }
        stream_copy_to_stream($source, $destination);
        fclose($destination);
        fclose($source);
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    public function store(
        string $directory,
        ?string $name = null,
        array $allowedMimes = [],
        int $maxBytes = 0,
        bool $preventOverwrite = true
    ): string
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Upload failed with error code ' . $this->error . '.');
        }

        if ($maxBytes > 0 && ($this->size ?? 0) > $maxBytes) {
            throw new RuntimeException('Uploaded file exceeds maximum size.');
        }

        if ($allowedMimes !== []) {
            $mime = $this->getClientMediaType() ?? '';
            if ($mime === '') {
                $mime = $this->detectMime();
            }
            if (!in_array($mime, $allowedMimes, true)) {
                throw new RuntimeException('Uploaded file MIME type is not allowed.');
            }
        }

        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        \Fnlla\Support\safe_mkdir($directory, 0755, true, 'uploads');

        $extension = $this->extension();
        $filename = $name !== null && $name !== '' ? $name : bin2hex(random_bytes(16));
        $filename = $this->sanitizeFilename($filename);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = bin2hex(random_bytes(16));
        }
        if ($extension !== '' && !str_contains($filename, '.')) {
            $filename .= '.' . $extension;
        }

        $target = $this->resolveTargetPath($directory, $filename, $preventOverwrite);
        $this->moveTo($target);
        return $target;
    }

    public function extension(): string
    {
        $name = $this->getClientFilename() ?? '';
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        return strtolower((string) $ext);
    }

    private function detectMime(): string
    {
        if ($this->tmpName === null || $this->tmpName === '' || !is_file($this->tmpName)) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }
        $mime = finfo_file($finfo, $this->tmpName) ?: 'application/octet-stream';
        finfo_close($finfo);
        return $mime;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = str_replace("\0", '', $name);
        $name = basename($name);
        $name = str_replace(['/', '\\'], '', $name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '', $name) ?? '';
        return $name;
    }

    private function resolveTargetPath(string $directory, string $filename, bool $preventOverwrite): string
    {
        $target = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!$preventOverwrite || !file_exists($target)) {
            return $target;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = $extension === '' ? $filename : substr($filename, 0, -(strlen($extension) + 1));

        for ($i = 0; $i < 10; $i++) {
            $suffix = bin2hex(random_bytes(4));
            $candidate = $stem . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $target = $directory . DIRECTORY_SEPARATOR . $candidate;
            if (!file_exists($target)) {
                return $target;
            }
        }

        throw new RuntimeException('Unable to generate a unique filename for upload.');
    }
}






