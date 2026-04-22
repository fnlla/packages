<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Log\Processor;

use Fnlla\Core\Container;
use Fnlla\Runtime\RequestContext;
use Monolog\LogRecord;
use Throwable;

final class RequestIdProcessor
{
    public function __construct(private ?Container $container = null)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = $this->resolveRequestId();
        if ($requestId === '') {
            return $record;
        }

        $extra = $record->extra;
        if (!array_key_exists('request_id', $extra)) {
            $extra['request_id'] = $requestId;
        }

        return $record->with(extra: $extra);
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
}
