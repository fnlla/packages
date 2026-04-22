<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Fnlla\Http\UploadedFile;

final class UploadPolicy
{
    /**
     * @param array{max_bytes?: int, allowed_mimes?: array<int, string>, blocked_extensions?: array<int, string>, max_filename_length?: int} $options
     * @return array<int, string>
     */
    public static function validate(UploadedFile $file, array $options = []): array
    {
        $maxBytes = (int) ($options['max_bytes'] ?? 10485760);
        $allowedMimes = $options['allowed_mimes'] ?? [];
        $blockedExtensions = $options['blocked_extensions'] ?? [];
        $maxName = (int) ($options['max_filename_length'] ?? 180);

        $errors = [];

        $size = $file->getSize();
        if ($size !== null && $maxBytes > 0 && $size > $maxBytes) {
            $errors[] = 'File exceeds maximum allowed size.';
        }

        $name = (string) ($file->getClientFilename() ?? '');
        if ($name !== '' && $maxName > 0 && strlen($name) > $maxName) {
            $errors[] = 'File name is too long.';
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($extension !== '' && in_array($extension, array_map('strtolower', (array) $blockedExtensions), true)) {
            $errors[] = 'This file type is not allowed.';
        }

        $detected = self::detectMime($file);
        $allowedList = array_filter(array_map('strtolower', (array) $allowedMimes));
        if ($allowedList !== [] && $detected !== null && !in_array(strtolower($detected), $allowedList, true)) {
            $errors[] = 'File mime type is not allowed.';
        }

        return array_values(array_unique($errors));
    }

    public static function sanitizeFilename(string $name, string $fallback = 'upload.bin'): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name) ?: $name;
        $name = trim((string) $name);

        if ($name === '' || $name === '.' || $name === '..') {
            return $fallback;
        }

        return $name;
    }

    private static function detectMime(UploadedFile $file): ?string
    {
        $client = $file->getClientMediaType();
        $client = is_string($client) && $client !== '' ? $client : null;

        try {
            $stream = $file->getStream();
        } catch (\Throwable $e) {
            return $client;
        }

        if (!method_exists($stream, 'getMetadata')) {
            return $client;
        }

        $uri = $stream->getMetadata('uri');
        if (!is_string($uri) || $uri === '' || !is_file($uri)) {
            return $client;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $uri);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        return $client;
    }
}
