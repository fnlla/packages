<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

use PDO;
use Throwable;

final class TransactionManager
{
    private int $level = 0;
    private array $afterCommitCallbacks = [];

    public function __construct(private ConnectionManager $connections)
    {
    }

    public function begin(): void
    {
        $pdo = $this->connections->connection();
        if ($this->level === 0) {
            $pdo->beginTransaction();
        }
        $this->level++;
    }

    public function commit(): void
    {
        if ($this->level === 0) {
            return;
        }

        $pdo = $this->connections->connection();
        $this->level--;
        if ($this->level === 0 && $pdo->inTransaction()) {
            $pdo->commit();
            $callbacks = $this->afterCommitCallbacks;
            $this->afterCommitCallbacks = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
        }
    }

    public function rollBack(): void
    {
        if ($this->level === 0) {
            return;
        }

        $pdo = $this->connections->connection();
        $this->level = 0;
        $this->afterCommitCallbacks = [];

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    public function transaction(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this->connections->connection());
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function afterCommit(callable $callback): void
    {
        if ($this->inTransaction()) {
            $this->afterCommitCallbacks[] = $callback;
            return;
        }

        $callback();
    }

    public function inTransaction(): bool
    {
        if ($this->level > 0) {
            return true;
        }

        return $this->connections->connection()->inTransaction();
    }
}
