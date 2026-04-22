<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Settings;

use Fnlla\Database\ConnectionManager;
use PDO;

final class SettingsRepository
{
    private string $table;

    public function __construct(private ConnectionManager $connections, string $table = 'settings')
    {
        $this->table = $table !== '' ? $table : 'settings';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $stmt = $this->pdo()->query('SELECT setting_key, setting_value FROM ' . $this->table);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $settings = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['setting_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }

        return $settings;
    }

    public function tableExists(): bool
    {
        $table = trim($this->table);
        if ($table === '') {
            return false;
        }

        try {
            $pdo = $this->pdo();
        } catch (\Throwable $e) {
            return false;
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table");
            $stmt->execute(['table' => $table]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && ($row['name'] ?? '') === $table;
        }

        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table');
            $stmt->execute(['table' => $table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->pdo()->prepare('SELECT setting_value FROM ' . $this->table . ' WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $default;
        }

        $value = (string) ($row['setting_value'] ?? '');
        return $value !== '' ? $value : $default;
    }

    public function set(string $key, ?string $value): void
    {
        $value = $value ?? '';
        $sql = 'INSERT INTO ' . $this->table . ' (setting_key, setting_value, updated_at)
                VALUES (:key, :value, :updated)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)';

        $driver = (string) $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = 'INSERT INTO ' . $this->table . ' (setting_key, setting_value, updated_at)
                    VALUES (:key, :value, :updated)
                    ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at';
        } elseif ($driver === 'pgsql') {
            $sql = 'INSERT INTO ' . $this->table . ' (setting_key, setting_value, updated_at)
                    VALUES (:key, :value, :updated)
                    ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = EXCLUDED.updated_at';
        }

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            'key' => $key,
            'value' => $value,
            'updated' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, string|null> $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value === null ? '' : (string) $value);
        }
    }

    public function delete(string $key): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM ' . $this->table . ' WHERE setting_key = :key');
        $stmt->execute(['key' => $key]);
    }

    private function pdo(): PDO
    {
        return $this->connections->connection();
    }
}
