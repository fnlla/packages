<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Notifications;

use DateTimeImmutable;
use Fnlla\Database\ConnectionManager;
use PDO;

final class NotificationRepository
{
    private PDO $pdo;

    public function __construct(ConnectionManager $connections, private string $table = 'notifications')
    {
        $this->pdo = $connections->connection();
        $this->table = $table !== '' ? $table : 'notifications';
    }

    public function create(array $data): int
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $payload = [
            'channel' => (string) ($data['channel'] ?? 'email'),
            'recipient' => (string) ($data['recipient'] ?? ''),
            'subject' => $data['subject'] ?? null,
            'body' => (string) ($data['body'] ?? ''),
            'status' => (string) ($data['status'] ?? 'pending'),
            'error' => $data['error'] ?? null,
            'metadata' => $this->encodeMeta($data['metadata'] ?? null),
            'created_at' => $data['created_at'] ?? $now,
            'sent_at' => $data['sent_at'] ?? null,
        ];

        $sql = 'INSERT INTO ' . $this->table . ' (channel, recipient, subject, body, status, error, metadata, created_at, sent_at) VALUES (:channel, :recipient, :subject, :body, :status, :error, :metadata, :created_at, :sent_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($payload);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $id, string $status, ?string $error = null, ?string $sentAt = null): void
    {
        $sql = 'UPDATE ' . $this->table . ' SET status = :status, error = :error, sent_at = :sent_at WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'error' => $error,
            'sent_at' => $sentAt,
        ]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function list(int $limit = 50, int $offset = 0): array
    {
        $limit = $limit > 0 ? $limit : 50;
        $offset = $offset >= 0 ? $offset : 0;
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->table . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map([$this, 'hydrate'], $rows);
    }

    private function hydrate(array $row): array
    {
        $row['metadata'] = $this->decodeMeta($row['metadata'] ?? null);
        return $row;
    }

    private function encodeMeta(mixed $meta): ?string
    {
        if ($meta === null) {
            return null;
        }
        if (is_string($meta)) {
            return $meta;
        }
        $encoded = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded !== false ? $encoded : null;
    }

    private function decodeMeta(mixed $meta): array
    {
        if ($meta === null || $meta === '') {
            return [];
        }
        if (is_array($meta)) {
            return $meta;
        }
        $decoded = json_decode((string) $meta, true);
        return is_array($decoded) ? $decoded : [];
    }
}


