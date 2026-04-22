<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Rag;

use Fnlla\Database\ConnectionManager;
use Fnlla\Database\Query;
use PDO;

final class RagRepository
{
    private PDO $pdo;
    private string $table;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(ConnectionManager $connections, array $config = [])
    {
        $this->pdo = $connections->connection();
        $table = (string) ($config['table'] ?? 'ai_embeddings');
        $this->table = $table !== '' ? $table : 'ai_embeddings';
    }

    public function ensureSchema(): void
    {
        RagSchema::ensure($this->pdo, $this->table);
    }

    public function countNamespace(string $namespace): int
    {
        $row = $this->query()
            ->select('COUNT(*) as count')
            ->where('namespace', $namespace)
            ->first();

        return is_array($row) ? (int) ($row['count'] ?? 0) : 0;
    }

    public function findByHash(string $namespace, string $hash, int $chunkIndex): ?array
    {
        $row = $this->query()
            ->select(['id', 'content_hash'])
            ->where('namespace', $namespace)
            ->where('content_hash', $hash)
            ->where('chunk_index', $chunkIndex)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function insert(array $payload): void
    {
        $this->query()->insert($payload);
    }

    public function deleteNamespace(string $namespace): int
    {
        return $this->query()
            ->where('namespace', $namespace)
            ->delete();
    }

    public function listByNamespace(string $namespace, int $limit = 200): array
    {
        $limit = max(1, $limit);
        return $this->query()
            ->select([
                'id',
                'namespace',
                'source_type',
                'source_id',
                'chunk_index',
                'chunk_total',
                'content',
                'content_hash',
                'embedding',
                'metadata',
                'created_at',
                'updated_at',
            ])
            ->where('namespace', $namespace)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    private function query(): Query
    {
        return (new Query($this->pdo))->table($this->table);
    }
}


