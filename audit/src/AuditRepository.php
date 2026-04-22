<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Audit;

use Fnlla\Database\ConnectionManager;
use PDO;

final class AuditRepository
{
    private string $table;

    public function __construct(private ConnectionManager $connections, string $table = 'audit_log')
    {
        $this->table = $table !== '' ? $table : 'audit_log';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO ' . $this->table . ' (user_id, action, entity_type, entity_id, ip_address, user_agent, metadata, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :ip_address, :user_agent, :metadata, :created_at)'
        );

        $metadata = $data['metadata'] ?? null;
        if (is_array($metadata)) {
            $metadata = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            'user_id' => $data['user_id'] ?? null,
            'action' => (string) ($data['action'] ?? ''),
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'metadata' => $metadata,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit = 50, ?int $userId = null): array
    {
        $limit = max(1, $limit);
        if ($userId === null) {
            $stmt = $this->pdo()->query('SELECT * FROM ' . $this->table . ' ORDER BY created_at DESC LIMIT ' . (int) $limit);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return is_array($rows) ? $rows : [];
        }

        $stmt = $this->pdo()->prepare('SELECT * FROM ' . $this->table . ' WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function pdo(): PDO
    {
        return $this->connections->connection();
    }
}




