<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

use PDO;
use RuntimeException;
use Fnlla\Runtime\Profiler;

final class Query
{
    private ?string $table = null;
    private array $columns = ['*'];
    private array $wheres = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(private PDO $pdo, private ?Grammar $grammar = null)
    {
        $this->grammar ??= new Grammar();
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereNull(string $column, bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'not' => $not,
        ];

        return $this;
    }

    public function whereIn(string $column, array $values, bool $not = false): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'not' => $not,
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function get(): array
    {
        $this->assertTable();
        [$sql, $params] = $this->grammar->compileSelect(
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->limit,
            $this->offset
        );

        $stmt = $this->pdo->prepare($sql);
        $this->executeTimed($stmt, $params);

        return $stmt->fetchAll();
    }

    public function first(): ?array
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function insert(array $values): int
    {
        $this->assertTable();
        if ($values === []) {
            throw new RuntimeException('Insert values cannot be empty.');
        }

        [$sql, $params] = $this->grammar->compileInsert($this->table, $values);
        $stmt = $this->pdo->prepare($sql);
        $this->executeTimed($stmt, $params);

        return $stmt->rowCount();
    }

    public function update(array $values): int
    {
        $this->assertTable();
        if ($values === []) {
            throw new RuntimeException('Update values cannot be empty.');
        }

        [$sql, $params] = $this->grammar->compileUpdate($this->table, $values, $this->wheres);
        $stmt = $this->pdo->prepare($sql);
        $this->executeTimed($stmt, $params);

        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $this->assertTable();
        [$sql, $params] = $this->grammar->compileDelete($this->table, $this->wheres);
        $stmt = $this->pdo->prepare($sql);
        $this->executeTimed($stmt, $params);

        return $stmt->rowCount();
    }

    private function executeTimed(\PDOStatement $stmt, array $params): void
    {
        $start = microtime(true);
        $stmt->execute($params);
        $duration = (microtime(true) - $start) * 1000;
        Profiler::recordDbTime($duration);
    }

    private function assertTable(): void
    {
        if ($this->table === null || $this->table === '') {
            throw new RuntimeException('Table is not set for the query.');
        }
    }
}
