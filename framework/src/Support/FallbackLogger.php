<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Support;

use Psr\Log\LoggerInterface;
use Fnlla\Runtime\RequestContext;

final class FallbackLogger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('critical', $message, $context);
    }

    public static function write(
        string $level,
        string $message,
        array $context = [],
        ?LoggerInterface $logger = null,
        ?string $path = null
    ): void
    {
        $logger = $logger ?? self::resolveLogger();
        if ($logger instanceof LoggerInterface) {
            $logger->log($level, $message, $context);
            return;
        }

        $payload = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('c'),
        ];
        $contextObj = RequestContext::current();
        $requestId = $contextObj instanceof RequestContext ? $contextObj->requestId() : '';
        if ($requestId !== '') {
            $payload['request_id'] = $requestId;
        }

        $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) {
            $line = $level . ': ' . $message;
        }

        $logPath = self::logPath($path);
        @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
    }

    private static function logPath(?string $path = null): string
    {
        if (is_string($path) && $path !== '') {
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            return $path;
        }

        $root = defined('APP_ROOT') ? APP_ROOT : getcwd();
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . DIRECTORY_SEPARATOR . 'app.log';
    }

    private static function resolveLogger(): ?LoggerInterface
    {
        if (!interface_exists(LoggerInterface::class)) {
            return null;
        }

        if (!function_exists('\\app')) {
            return null;
        }

        try {
            $app = \app();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$app instanceof \Fnlla\Core\Container) {
            return null;
        }

        if (!$app->has(LoggerInterface::class)) {
            return null;
        }

        try {
            $logger = $app->make(LoggerInterface::class);
        } catch (\Throwable $e) {
            return null;
        }

        return $logger instanceof LoggerInterface ? $logger : null;
    }
}
