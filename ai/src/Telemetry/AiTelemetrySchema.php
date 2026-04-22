<?php
/**
 * fnlla - AI-assisted PHP framework.
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Ai\Telemetry;

use PDO;

final class AiTelemetrySchema
{
    public static function ensure(PDO $pdo, string $table = 'ai_runs'): void
    {
        $table = $table !== '' ? $table : 'ai_runs';
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id SERIAL PRIMARY KEY, provider VARCHAR(60) NULL, model VARCHAR(120) NULL, status VARCHAR(30) NOT NULL, temperature REAL NULL, max_output_tokens INT NULL, input_text TEXT NULL, output_text TEXT NULL, context_text TEXT NULL, sources JSON NULL, error TEXT NULL, meta JSON NULL, created_at TIMESTAMP NOT NULL, updated_at TIMESTAMP NOT NULL)');
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INTEGER PRIMARY KEY AUTOINCREMENT, provider TEXT NULL, model TEXT NULL, status TEXT NOT NULL, temperature REAL NULL, max_output_tokens INTEGER NULL, input_text TEXT NULL, output_text TEXT NULL, context_text TEXT NULL, sources TEXT NULL, error TEXT NULL, meta TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS ' . $table . ' (id INT AUTO_INCREMENT PRIMARY KEY, provider VARCHAR(60) NULL, model VARCHAR(120) NULL, status VARCHAR(30) NOT NULL, temperature FLOAT NULL, max_output_tokens INT NULL, input_text TEXT NULL, output_text TEXT NULL, context_text TEXT NULL, sources JSON NULL, error TEXT NULL, meta JSON NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)');
    }
}


