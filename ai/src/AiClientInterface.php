<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai;

interface AiClientInterface
{
    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function responses(array $payload): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function embeddings(array $payload): array;

    /**
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function models(): array;

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, status: int, data: array<mixed>|null, error: string}
     */
    public function realtimeClientSecret(array $payload = []): array;
}


