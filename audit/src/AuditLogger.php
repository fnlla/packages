<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

final class AuditLogger
{
    public function __construct(
        private AuditRepository $repository,
        private ?AuditContextInterface $context = null
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $overrides
     */
    public function record(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        array $metadata = [],
        array $overrides = []
    ): void {
        $context = $this->context ?? new ServerAuditContext();

        $data = [
            'user_id' => $overrides['user_id'] ?? $context->userId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $overrides['ip_address'] ?? $context->ipAddress(),
            'user_agent' => $overrides['user_agent'] ?? $context->userAgent(),
            'metadata' => $metadata,
        ];

        if (isset($overrides['created_at'])) {
            $data['created_at'] = $overrides['created_at'];
        }

        try {
            $this->repository->create($data);
        } catch (\Throwable) {
            // Avoid blocking the request if logging fails.
        }
    }
}




