<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Analytics;

use Fnlla\Contracts\Log\LoggerInterface;
use Throwable;

final class AnalyticsClient
{
    public function __construct(private ?LoggerInterface $logger = null)
    {
    }

    public function track(string $event, array $context = []): void
    {
        if ($event === '') {
            return;
        }

        if (!$this->logger instanceof LoggerInterface) {
            return;
        }

        try {
            $this->logger->info('analytics:' . $event, $context);
        } catch (Throwable) {
            // Ignore logging errors.
        }
    }
}
