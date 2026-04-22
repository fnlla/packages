<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Log;

use Fnlla\Core\Container;
use Fnlla\Log\Processor\RequestIdProcessor;
use Fnlla\Log\Processor\StaticContextProcessor;
use Fnlla\Support\Env;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public function __construct(private array $config = [], private ?Container $container = null)
    {
    }

    public function make(string $channel = 'app'): LoggerInterface
    {
        $path = (string) ($this->config['path'] ?? $this->env('LOG_PATH', ''));
        if ($path === '') {
            $path = $this->defaultLogPath();
        }

        $level = (string) ($this->config['level'] ?? $this->env('LOG_LEVEL', 'info'));
        $handler = new StreamHandler($path, Logger::toMonologLevel($level));
        $handler->setFormatter($this->buildFormatter());

        $logger = new Logger($channel, [$handler]);
        foreach ($this->buildProcessors() as $processor) {
            $logger->pushProcessor($processor);
        }

        return $logger;
    }

    private function defaultLogPath(): string
    {
        $appRoot = $this->env('APP_ROOT');
        if ($appRoot !== '') {
            return rtrim($appRoot, '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
        }

        return getcwd() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log';
    }

    private function buildFormatter(): LineFormatter|JsonFormatter
    {
        $format = strtolower((string) ($this->config['format'] ?? $this->env('LOG_FORMAT', 'line')));
        if (!in_array($format, ['line', 'json'], true)) {
            $format = 'line';
        }

        if ($format === 'json') {
            $formatter = new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true);
            return $formatter;
        }

        return new LineFormatter(
            '[%datetime%] %channel%.%level_name%: %message% %context% %extra%',
            null,
            true,
            true
        );
    }

    private function buildProcessors(): array
    {
        $processors = [];

        $includeRequestId = $this->toBool($this->config['include_request_id'] ?? $this->env('LOG_REQUEST_ID', true), true);
        if ($includeRequestId) {
            $processors[] = new RequestIdProcessor($this->container);
        }

        $context = $this->config['context'] ?? [];
        if (is_array($context) && $context !== []) {
            $processors[] = new StaticContextProcessor($context);
        }

        return $processors;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }

    private function env(string $key, mixed $default = ''): string
    {
        return (string) Env::get($key, $default);
    }
}
