<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Console\Commands;

use Fnlla\Console\ConsoleIO;
use RuntimeException;

abstract class AbstractMakeCommand
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

    protected function resolveClass(string $name, string $baseNamespace, string $baseDir, string $suffix = ''): array
    {
        $name = trim($name);
        $name = trim($name, '/\\');
        $name = str_replace('\\', '/', $name);
        $segments = array_filter(explode('/', $name), fn ($seg) => $seg !== '');
        $segments = array_map([$this, 'studly'], $segments);

        $class = array_pop($segments) ?? '';
        if ($class === '') {
            throw new RuntimeException('Class name is empty.');
        }

        if ($suffix !== '' && !str_ends_with($class, $suffix)) {
            $class .= $suffix;
        }

        $namespace = $baseNamespace;
        if ($segments !== []) {
            $namespace .= '\\' . implode('\\', $segments);
        }

        $path = $baseDir;
        if ($segments !== []) {
            $path .= DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
        }
        $path .= DIRECTORY_SEPARATOR . $class . '.php';

        return [$namespace, $class, $path];
    }

    protected function renderTemplate(string $templatePath, array $vars): string
    {
        if (!is_file($templatePath)) {
            throw new RuntimeException('Template not found: ' . $templatePath);
        }

        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException('Unable to read template: ' . $templatePath);
        }

        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }

    protected function writeFile(ConsoleIO $io, string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $dir);
        }

        if (is_file($path)) {
            throw new RuntimeException('File already exists: ' . $path);
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write file: ' . $path);
        }

        $io->line('Created: ' . $path);
    }
}
