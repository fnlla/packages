<?php

/**
 * FnllaPHP framework
 * Licensed under the Proprietary License.
 */

declare(strict_types=1);

namespace Fnlla\Debugbar;

final class DebugbarCollector
{
    private static array $queries = [];
    private static array $messages = [];
    private static array $errors = [];
    private static array $timeline = [];
    private static ?float $startTime = null;

    public static function init(): void
    {
        if (self::$startTime !== null) {
            return;
        }
        if (defined('APP_START_TIME')) {
            self::$startTime = (float) APP_START_TIME;
        } else {
            self::$startTime = microtime(true);
        }
    }

    public static function addQuery(string $sql, array $params, float $timeMs, int $rowCount = 0, string $source = 'pdo'): void
    {
        self::init();
        self::$queries[] = [
            'sql' => $sql,
            'params' => $params,
            'time_ms' => round($timeMs, 2),
            'row_count' => $rowCount,
            'source' => $source,
        ];
    }

    public static function addMessage(string $level, string $message, array $context = []): void
    {
        self::init();
        self::$messages[] = [
            'time' => date('H:i:s'),
            'level' => strtolower($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    public static function addError(string $type, string $message, string $file, int $line, ?string $trace = null): void
    {
        self::init();
        self::$errors[] = [
            'time' => date('H:i:s'),
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => $trace,
        ];
    }

    public static function mark(string $label, ?float $timeMs = null): void
    {
        self::init();
        if ($timeMs === null) {
            $timeMs = (microtime(true) - (self::$startTime ?? microtime(true))) * 1000;
        }
        self::$timeline[] = [
            'label' => $label,
            'ms' => round($timeMs, 2),
        ];
    }

    public static function queries(): array
    {
        return self::$queries;
    }

    public static function messages(): array
    {
        return self::$messages;
    }

    public static function errors(): array
    {
        return self::$errors;
    }

    public static function timeline(): array
    {
        return self::$timeline;
    }

    public static function requestTimeMs(): float
    {
        self::init();
        return round((microtime(true) - (self::$startTime ?? microtime(true))) * 1000, 2);
    }

    public static function reset(): void
    {
        self::$queries = [];
        self::$messages = [];
        self::$errors = [];
        self::$timeline = [];
        self::$startTime = null;
    }
}
