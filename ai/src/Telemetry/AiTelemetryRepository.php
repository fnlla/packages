<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Telemetry;

use Fnlla\Database\ConnectionManager;
use Fnlla\Database\Query;
use PDO;

final class AiTelemetryRepository
{
    private PDO $pdo;
    private string $table;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(ConnectionManager $connections, array $config = [])
    {
        $this->pdo = $connections->connection();
        $table = (string) ($config['table'] ?? 'ai_runs');
        $this->table = $table !== '' ? $table : 'ai_runs';
    }

    public function ensureSchema(): void
    {
        AiTelemetrySchema::ensure($this->pdo, $this->table);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function insert(array $payload): int
    {
        return $this->query()->insert($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $id, array $payload): int
    {
        return $this->query()
            ->where('id', $id)
            ->update($payload);
    }

    private function query(): Query
    {
        return (new Query($this->pdo))->table($this->table);
    }
}


