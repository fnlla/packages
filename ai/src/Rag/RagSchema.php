<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Rag;

use PDO;

final class RagSchema
{
    public static function ensure(PDO $pdo, string $table = 'ai_embeddings'): void
    {
        $table = $table !== '' ? $table : 'ai_embeddings';
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id SERIAL PRIMARY KEY, namespace VARCHAR(80) NOT NULL, source_type VARCHAR(80) NULL, source_id VARCHAR(120) NULL, chunk_index INT NOT NULL, chunk_total INT NOT NULL, content TEXT NOT NULL, content_hash VARCHAR(64) NOT NULL, embedding JSON NOT NULL, metadata JSON NULL, created_at TIMESTAMP NOT NULL, updated_at TIMESTAMP NOT NULL)');
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, namespace TEXT NOT NULL, source_type TEXT NULL, source_id TEXT NULL, chunk_index INTEGER NOT NULL, chunk_total INTEGER NOT NULL, content TEXT NOT NULL, content_hash TEXT NOT NULL, embedding TEXT NOT NULL, metadata TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, namespace VARCHAR(80) NOT NULL, source_type VARCHAR(80) NULL, source_id VARCHAR(120) NULL, chunk_index INT NOT NULL, chunk_total INT NOT NULL, content TEXT NOT NULL, content_hash VARCHAR(64) NOT NULL, embedding JSON NOT NULL, metadata JSON NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }
}


