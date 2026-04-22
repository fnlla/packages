<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

final class Dotenv
{
    private bool $usePutenv = false;

    public function usePutenv(bool $use): void
    {
        $this->usePutenv = $use;
    }

    public function bootEnv(string $path): void
    {
        $this->loadEnv($path);
    }

    public function loadEnv(string $path): void
    {
        $this->load($path);
    }

    public function load(string ...$paths): void
    {
        $values = [];

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '' || !is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $this->parseAndSet($contents, $values);
        }
    }

    private function parseAndSet(string $contents, array &$values): void
    {
        $lines = preg_split("/\\R/", $contents);
        if ($lines === false) {
            return;
        }

        $pendingKey = null;
        $pendingValue = '';
        $pendingQuote = '';

        foreach ($lines as $line) {
            if ($pendingKey !== null) {
                $pendingValue .= "\n" . $line;
                if ($this->hasClosingQuote($pendingValue, $pendingQuote)) {
                    $value = $this->parseQuoted($pendingValue, $pendingQuote, $values);
                    $this->setEnv($pendingKey, $value, $values);
                    $pendingKey = null;
                    $pendingValue = '';
                    $pendingQuote = '';
                }
                continue;
            }

            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (str_starts_with($trimmed, 'export ')) {
                $trimmed = ltrim(substr($trimmed, 7));
            }

            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $pos));
            if ($key === '') {
                continue;
            }

            $valuePart = ltrim(substr($trimmed, $pos + 1));
            if ($valuePart !== '' && ($valuePart[0] === '"' || $valuePart[0] === "'")) {
                $quote = $valuePart[0];
                if ($this->hasClosingQuote($valuePart, $quote)) {
                    $value = $this->parseQuoted($valuePart, $quote, $values);
                    $this->setEnv($key, $value, $values);
                } else {
                    $pendingKey = $key;
                    $pendingValue = $valuePart;
                    $pendingQuote = $quote;
                }
                continue;
            }

            $value = $this->stripInlineComment($valuePart);
            $value = trim($value);
            $value = $this->interpolate($value, $values);
            $this->setEnv($key, $value, $values);
        }

        if ($pendingKey !== null) {
            $value = $this->parseQuoted($pendingValue, $pendingQuote, $values);
            $this->setEnv($pendingKey, $value, $values);
        }
    }

    private function hasClosingQuote(string $value, string $quote): bool
    {
        return $this->findClosingQuote($value, $quote) !== null;
    }

    private function findClosingQuote(string $value, string $quote): ?int
    {
        $length = strlen($value);
        for ($i = 1; $i < $length; $i++) {
            if ($value[$i] !== $quote) {
                continue;
            }
            if ($quote === '"' && $this->isEscaped($value, $i)) {
                continue;
            }
            return $i;
        }

        return null;
    }

    private function isEscaped(string $value, int $pos): bool
    {
        $count = 0;
        for ($i = $pos - 1; $i >= 0; $i--) {
            if ($value[$i] !== '\\') {
                break;
            }
            $count++;
        }

        return ($count % 2) === 1;
    }

    private function parseQuoted(string $value, string $quote, array $values): string
    {
        $pos = $this->findClosingQuote($value, $quote);
        if ($pos === null) {
            $inner = substr($value, 1);
        } else {
            $inner = substr($value, 1, $pos - 1);
        }

        if ($quote === '"') {
            $inner = str_replace(
                ['\\n', '\\r', '\\t', '\\"', '\\\\', '\\$'],
                ["\n", "\r", "\t", '"', '\\', '$'],
                $inner
            );
        }

        return $this->interpolate($inner, $values);
    }

    private function stripInlineComment(string $value): string
    {
        $parts = preg_split('/\\s+#/', $value, 2);
        if (!is_array($parts)) {
            return $value;
        }

        return $parts[0] ?? $value;
    }

    private function interpolate(string $value, array $values): string
    {
        return (string) preg_replace_callback(
            '/\\$\\{([A-Z0-9_]+)(?::-([^}]*))?\\}/i',
            function (array $matches) use ($values): string {
                $name = $matches[1];
                $default = $matches[2] ?? null;
                $existing = $this->existingValue($name, $values);
                if ($existing !== null) {
                    return $existing;
                }
                if ($default !== null) {
                    return $default;
                }
                return '';
            },
            $value
        );
    }

    private function setEnv(string $key, string $value, array &$values): void
    {
        $existing = $this->existingValue($key, $values);
        if ($existing !== null) {
            $values[$key] = $existing;
            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        if ($this->usePutenv) {
            putenv($key . '=' . $value);
        }

        $values[$key] = $value;
    }

    private function existingValue(string $key, array $values): ?string
    {
        $env = getenv($key);
        if ($env !== false) {
            return (string) $env;
        }
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        if (array_key_exists($key, $values)) {
            return (string) $values[$key];
        }

        return null;
    }
}
