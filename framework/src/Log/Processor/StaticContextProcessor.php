<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Log\Processor;

use Monolog\LogRecord;

final class StaticContextProcessor
{
    public function __construct(private array $context)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->context === []) {
            return $record;
        }

        $extra = $record->extra;
        foreach ($this->context as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!array_key_exists($key, $extra)) {
                $extra[$key] = $value;
            }
        }

        return $record->with(extra: $extra);
    }
}
