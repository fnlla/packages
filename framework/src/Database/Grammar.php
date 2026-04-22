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

final class Grammar
{
    public function compileSelect(
        string $table,
        array $columns,
        array $wheres,
        array $orders,
        ?int $limit,
        ?int $offset
    ): array {
        $params = [];
        $sql = 'SELECT ' . $this->columnList($columns) . ' FROM ' . $this->wrapIdentifier($table);
        $sql .= $this->compileWheres($wheres, $params);

        if ($orders !== []) {
            $sql .= ' ORDER BY ' . $this->orderList($orders);
        }

        $limitValue = $limit;
        if ($limitValue === null && $offset !== null) {
            $limitValue = PHP_INT_MAX;
        }

        if ($limitValue !== null) {
            $sql .= ' LIMIT ' . (int) $limitValue;
        }

        if ($offset !== null) {
            $sql .= ' OFFSET ' . (int) $offset;
        }

        return [$sql, $params];
    }

    public function compileInsert(string $table, array $values): array
    {
        $columns = array_keys($values);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = 'INSERT INTO ' . $this->wrapIdentifier($table)
            . ' (' . $this->columnList($columns) . ')'
            . ' VALUES (' . implode(', ', $placeholders) . ')';

        return [$sql, array_values($values)];
    }

    public function compileUpdate(string $table, array $values, array $wheres): array
    {
        $params = [];
        $sets = [];

        foreach ($values as $column => $value) {
            $sets[] = $this->wrapIdentifier((string) $column) . ' = ?';
            $params[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrapIdentifier($table) . ' SET ' . implode(', ', $sets);
        $sql .= $this->compileWheres($wheres, $params);

        return [$sql, $params];
    }

    public function compileDelete(string $table, array $wheres): array
    {
        $params = [];
        $sql = 'DELETE FROM ' . $this->wrapIdentifier($table);
        $sql .= $this->compileWheres($wheres, $params);

        return [$sql, $params];
    }

    private function compileWheres(array $wheres, array &$params): string
    {
        if ($wheres === []) {
            return '';
        }

        $clauses = [];

        foreach ($wheres as $where) {
            $type = $where['type'] ?? 'basic';

            if ($type === 'null') {
                $not = (bool) ($where['not'] ?? false);
                $clauses[] = $this->wrapIdentifier((string) $where['column'])
                    . ($not ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }

            if ($type === 'in') {
                $values = $where['values'] ?? [];
                if ($values === []) {
                    $clauses[] = '0=1';
                    continue;
                }
                $not = (bool) ($where['not'] ?? false);
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $clauses[] = $this->wrapIdentifier((string) $where['column'])
                    . ($not ? ' NOT IN (' : ' IN (') . $placeholders . ')';
                foreach ($values as $value) {
                    $params[] = $value;
                }
                continue;
            }

            $column = $this->wrapIdentifier((string) $where['column']);
            $operator = (string) ($where['operator'] ?? '=');
            $clauses[] = $column . ' ' . $operator . ' ?';
            $params[] = $where['value'] ?? null;
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    private function columnList(array $columns): string
    {
        if ($columns === []) {
            return '*';
        }

        $wrapped = [];
        foreach ($columns as $column) {
            $wrapped[] = $this->wrapIdentifier((string) $column);
        }

        return implode(', ', $wrapped);
    }

    private function orderList(array $orders): string
    {
        $parts = [];
        foreach ($orders as $order) {
            $direction = strtoupper((string) ($order['direction'] ?? 'ASC'));
            if ($direction !== 'DESC') {
                $direction = 'ASC';
            }
            $parts[] = $this->wrapIdentifier((string) $order['column']) . ' ' . $direction;
        }

        return implode(', ', $parts);
    }

    private function wrapIdentifier(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '' || str_contains($trimmed, '(') || str_contains($trimmed, ' ')) {
            return $trimmed;
        }

        $parts = explode('.', $trimmed);
        $wrapped = [];

        foreach ($parts as $part) {
            if ($part === '*') {
                $wrapped[] = $part;
                continue;
            }
            $wrapped[] = $this->quoteIdentifier($part);
        }

        return implode('.', $wrapped);
    }

    private function quoteIdentifier(string $identifier): string
    {
        $identifier = str_replace(["\"", '`'], '', $identifier);
        return '"' . $identifier . '"';
    }
}
