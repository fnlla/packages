<?php

/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Contracts\Log;

use Throwable;

/**
 * @api
 */
interface ErrorReporterInterface
{
    /**
     * Report a throwable to an external system (e.g. Sentry, Bugsnag).
     *
     * @param array<string, mixed> $context
     */
    public function report(Throwable $exception, array $context = []): void;
}

