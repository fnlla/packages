<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Log;

use Fnlla\Contracts\Log\LoggerInterface;
use Fnlla\Core\Container;
use Fnlla\Runtime\RequestContext;
use Fnlla\Support\Psr\Log\LogLevel;
use Throwable;

final class Logger implements LoggerInterface
{
    private array $levels = [
        LogLevel::DEBUG => 100,
        LogLevel::INFO => 200,
        LogLevel::NOTICE => 250,
        LogLevel::WARNING => 300,
        LogLevel::ERROR => 400,
        LogLevel::CRITICAL => 500,
        LogLevel::ALERT => 550,
        LogLevel::EMERGENCY => 600,
    ];

    private string $filePath;
    private int $threshold;
    private string $basePath;
    private ?Container $container;

    public function __construct(string $path, string $level = 'info', string $basePath = '', ?Container $container = null)
    {
        $level = strtolower($level);
        $this->threshold = $this->levels[$level] ?? $this->levels['info'];
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->container = $container;

        $path = rtrim($path, DIRECTORY_SEPARATOR);
        if ($path === '' || str_ends_with($path, DIRECTORY_SEPARATOR) || !str_contains($path, '.log')) {
            $dir = $path === '' ? $this->defaultLogDir() : $path;
            \Fnlla\Support\safe_mkdir($dir, 0755, true, 'logs');
            $this->filePath = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        } else {
            $dir = dirname($path);
            \Fnlla\Support\safe_mkdir($dir, 0755, true, 'logs');
            $this->filePath = $path;
        }
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        $level = strtolower($level);
        $levelValue = $this->levels[$level] ?? $this->levels['info'];
        if ($levelValue < $this->threshold) {
            return;
        }

        $line = $this->formatLine($level, (string) $message, $context);
        file_put_contents($this->filePath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function formatLine(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $requestId = $this->resolveRequestId();
        $context = $this->normalizeContext($context);
        $contextText = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $prefix = '[' . strtoupper($level) . ']';
        if ($requestId !== '') {
            $prefix .= ' [' . $requestId . ']';
        }

        return $timestamp . ' ' . $prefix . ' ' . $message . $contextText;
    }

    private function defaultLogDir(): string
    {
        if ($this->basePath !== '') {
            return $this->basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        return 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }

    private function resolveRequestId(): string
    {
        if (!$this->container instanceof Container) {
            return '';
        }

        if (!$this->container->has(RequestContext::class)) {
            return '';
        }

        try {
            $context = $this->container->make(RequestContext::class);
            return $context instanceof RequestContext ? $context->requestId() : '';
        } catch (Throwable $e) {
            return '';
        }
    }

    private function normalizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_resource($value)) {
                $context[$key] = 'resource';
            } elseif (is_object($value)) {
                $context[$key] = method_exists($value, '__toString') ? (string) $value : get_class($value);
            }
        }
        return $context;
    }
}
