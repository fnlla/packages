<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Storage;

use RuntimeException;

final class ImagePipeline
{
    public function resize(string $sourcePath, string $targetPath, int $width, int $height): bool
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
            throw new RuntimeException('GD extension is required for image processing.');
        }

        if (!is_file($sourcePath)) {
            throw new RuntimeException('Source image not found: ' . $sourcePath);
        }

        $data = file_get_contents($sourcePath);
        if ($data === false) {
            throw new RuntimeException('Unable to read source image.');
        }

        $image = imagecreatefromstring($data);
        if ($image === false) {
            throw new RuntimeException('Unsupported image format.');
        }

        $resized = imagescale($image, $width, $height);
        if ($resized === false) {
            imagedestroy($image);
            throw new RuntimeException('Image resize failed.');
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $result = imagepng($resized, $targetPath);
        imagedestroy($image);
        imagedestroy($resized);

        return $result;
    }
}
