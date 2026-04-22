<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm;

use PDO;
use RuntimeException;
use Fnlla\Orm\ModelNotFoundException;

final class QueryBuilder
{
    private array $columns = ['*'];
    private array $wheres = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $with = [];
    private array $withCount = [];
    private array $withAggregates = [];
    private ?string $softDeleteColumn = null;
    private bool $includeTrashed = false;
    private bool $onlyTrashed = false;
    private bool $distinct = false;
    private array $globalScopes = [];
    private array $disabledGlobalScopes = [];
    private bool $applyGlobalScopes = true;
    private bool $globalScopesApplied = false;

    public function __construct(
        private PDO $pdo,
        private string $table,
        private string $modelClass
    )
    {
    }

    public function select(array|string $columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : [$columns];
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            if ($operatorOrValue === null) {
                return $this->whereNull($column);
            }
            if (in_array($operatorOrValue, ['!=', '<>'], true)) {
                return $this->whereNotNull($column);
            }
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'boolean' => 'and',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'boolean' => 'and',
            'column' => $column,
            'not' => false,
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'boolean' => 'and',
            'column' => $column,
            'not' => true,
        ];

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'boolean' => 'or',
            'column' => $column,
            'not' => false,
        ];

        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'boolean' => 'or',
            'column' => $column,
            'not' => true,
        ];

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            if ($operatorOrValue === null) {
                return $this->orWhereNull($column);
            }
            if (in_array($operatorOrValue, ['!=', '<>'], true)) {
                return $this->orWhereNotNull($column);
            }
            $operator = '=';
            $value = $operatorOrValue;
        } else {
            $operator = (string) $operatorOrValue;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'boolean' => 'or',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', $value);
    }

    public function orWhereLike(string $column, string $value): self
    {
        return $this->orWhere($column, 'LIKE', $value);
    }

    public function whereNotLike(string $column, string $value): self
    {
        return $this->where($column, 'NOT LIKE', $value);
    }

    public function orWhereNotLike(string $column, string $value): self
    {
        return $this->orWhere($column, 'NOT LIKE', $value);
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'boolean' => 'and',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'boolean' => 'or',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'boolean' => 'and',
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'boolean' => 'or',
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'boolean' => 'and',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    public function orWhereBetween(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'boolean' => 'or',
            'column' => $column,
            'values' => $values,
            'not' => false,
        ];

        return $this;
    }

    public function whereNotBetween(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'boolean' => 'and',
            'column' => $column,
            'values' => $values,
            'not' => true,
        ];

        return $this;
    }

    public function orWhereNotBetween(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'boolean' => 'or',
            'column' => $column,
            'values' => $values,
            'not' => true,
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

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderByDesc($column);
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
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

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function distinct(bool $value = true): self
    {
        $this->distinct = $value;
        return $this;
    }

    public function with(array|string $relations): self
    {
        $items = is_array($relations) ? $relations : [$relations];
        foreach ($items as $relation) {
            if (is_string($relation) && $relation !== '') {
                $this->with[] = $relation;
            }
        }
        return $this;
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default !== null) {
            $default($this, $condition);
        }

        return $this;
    }

    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    public function setSoftDeleteColumn(?string $column): self
    {
        $this->softDeleteColumn = $column !== '' ? $column : null;
        return $this;
    }

    public function withTrashed(): self
    {
        $this->includeTrashed = true;
        $this->onlyTrashed = false;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        return $this;
    }

    public function withoutTrashed(): self
    {
        $this->includeTrashed = false;
        $this->onlyTrashed = false;
        return $this;
    }

    public function get(): array
    {
        [$sql, $params] = $this->compileSelect();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->newModelInstance($row);
        }

        if ($this->with !== []) {
            $models = $this->eagerLoad($models, $this->with);
        }

        return $models;
    }

    public function first(): ?Model
    {
        $this->limit(1);
        $rows = $this->get();
        return $rows[0] ?? null;
    }

    public function firstOrFail(): Model
    {
        $model = $this->first();
        if ($model === null) {
            throw new ModelNotFoundException($this->modelClass . ' not found.');
        }
        return $model;
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        $total = $this->count();
        $this->limit($perPage);
        $this->offset(($page - 1) * $perPage);
        $items = $this->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function count(): int
    {
        [$whereSql, $params] = $this->compileWhere();
        $sql = 'SELECT COUNT(*) as aggregate FROM ' . $this->table;
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $value = $this->aggregateValue('SUM', $column);
        return $value === null ? 0.0 : (float) $value;
    }

    public function avg(string $column): float
    {
        $value = $this->aggregateValue('AVG', $column);
        return $value === null ? 0.0 : (float) $value;
    }

    public function min(string $column): mixed
    {
        return $this->aggregateValue('MIN', $column);
    }

    public function max(string $column): mixed
    {
        return $this->aggregateValue('MAX', $column);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return $this->count() === 0;
    }

    private function aggregateValue(string $function, string $column): mixed
    {
        $function = strtoupper($function);
        $column = $column === '' ? '*' : $column;

        [$whereSql, $params] = $this->compileWhere();
        $sql = 'SELECT ' . $function . '(' . $column . ') as aggregate FROM ' . $this->table;
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row['aggregate'] ?? null;
    }

    public function value(string $column): mixed
    {
        $model = $this->select([$column])->first();
        return $model instanceof Model ? $model->getAttribute($column) : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $items = $this->select([$column])->get();
        $result = [];
        foreach ($items as $item) {
            if (!$item instanceof Model) {
                continue;
            }
            $value = $item->getAttribute($column);
            if ($key === null) {
                $result[] = $value;
                continue;
            }
            $keyValue = $item->getAttribute($key);
            if ($keyValue !== null) {
                $result[$keyValue] = $value;
            }
        }
        return $result;
    }

    public function update(array $values): int
    {
        if ($values === []) {
            throw new RuntimeException('Update values cannot be empty.');
        }

        $parts = [];
        $params = [];
        foreach ($values as $column => $value) {
            $parts[] = $column . ' = ?';
            $params[] = $value;
        }

        [$whereSql, $whereParams] = $this->compileWhere();
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $parts);
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
            $params = array_merge($params, $whereParams);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        [$whereSql, $params] = $this->compileWhere();
        $sql = 'DELETE FROM ' . $this->table;
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    private function compileSelect(): array
    {
        $selectColumns = $this->columns !== [] ? $this->columns : ['*'];
        $selectParams = [];

        foreach ($this->withCount as $item) {
            $relation = (string) ($item['relation'] ?? '');
            if ($relation === '') {
                continue;
            }
            $callback = $item['callback'] ?? null;
            $alias = (string) ($item['alias'] ?? ($relation . '_count'));
            [$countSql, $params] = $this->compileRelationCount($relation, is_callable($callback) ? $callback : null);
            $selectColumns[] = '(' . $countSql . ') AS ' . $alias;
            $selectParams = array_merge($selectParams, $params);
        }

        foreach ($this->withAggregates as $item) {
            $relation = (string) ($item['relation'] ?? '');
            $column = (string) ($item['column'] ?? '');
            $function = (string) ($item['function'] ?? '');
            if ($relation === '' || $column === '' || $function === '') {
                continue;
            }
            $callback = $item['callback'] ?? null;
            $alias = (string) ($item['alias'] ?? $this->sanitizeAlias($relation . '_' . strtolower($function)));
            [$aggSql, $params] = $this->compileRelationAggregate($relation, $column, $function, is_callable($callback) ? $callback : null);
            $selectColumns[] = '(' . $aggSql . ') AS ' . $alias;
            $selectParams = array_merge($selectParams, $params);
        }

        $columns = implode(', ', $selectColumns);
        $select = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql = $select . $columns . ' FROM ' . $this->table;

        [$whereSql, $params] = $this->compileWhere();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($this->orders !== []) {
            $parts = [];
            foreach ($this->orders as $order) {
                $parts[] = $order['column'] . ' ' . $order['direction'];
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return [$sql, array_merge($selectParams, $params)];
    }

    private function compileWhere(): array
    {
        $this->applyGlobalScopesIfNeeded();
        $wheres = $this->wheres;

        if ($this->softDeleteColumn !== null) {
            if ($this->onlyTrashed) {
                $wheres[] = [
                    'type' => 'null',
                    'boolean' => 'and',
                    'column' => $this->softDeleteColumn,
                    'not' => true,
                ];
            } elseif (!$this->includeTrashed) {
                $wheres[] = [
                    'type' => 'null',
                    'boolean' => 'and',
                    'column' => $this->softDeleteColumn,
                    'not' => false,
                ];
            }
        }

        if ($wheres === []) {
            return ['', []];
        }

        $parts = [];
        $params = [];
        foreach ($wheres as $index => $where) {
            $boolean = $index === 0 ? '' : strtoupper($where['boolean']) . ' ';
            if ($where['type'] === 'basic') {
                $parts[] = $boolean . $where['column'] . ' ' . $where['operator'] . ' ?';
                $params[] = $where['value'];
                continue;
            }

            if ($where['type'] === 'null') {
                $parts[] = $boolean . $where['column'] . ($where['not'] ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }

            if ($where['type'] === 'in') {
                $values = $where['values'] ?? [];
                if ($values === []) {
                    $parts[] = $boolean . '0=1';
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($values), '?'));
                $not = !empty($where['not']);
                $parts[] = $boolean . $where['column'] . ($not ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')';
                foreach ($values as $value) {
                    $params[] = $value;
                }
                continue;
            }

            if ($where['type'] === 'between') {
                $values = $where['values'] ?? [];
                $lower = $values[0] ?? null;
                $upper = $values[1] ?? null;
                $not = !empty($where['not']);
                $parts[] = $boolean . $where['column'] . ($not ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?');
                $params[] = $lower;
                $params[] = $upper;
                continue;
            }

            if ($where['type'] === 'raw') {
                $sql = (string) ($where['sql'] ?? '');
                if ($sql === '') {
                    continue;
                }
                $parts[] = $boolean . $sql;
                foreach ($where['params'] ?? [] as $value) {
                    $params[] = $value;
                }
                continue;
            }

            if ($where['type'] === 'exists') {
                $parts[] = $boolean . ($where['not'] ? 'NOT EXISTS (' : 'EXISTS (') . $where['sql'] . ')';
                foreach ($where['params'] as $value) {
                    $params[] = $value;
                }
                continue;
            }
        }

        return [implode(' ', $parts), $params];
    }

    public function whereExists(string $sql, array $params = [], bool $not = false, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'exists',
            'boolean' => $boolean,
            'sql' => $sql,
            'params' => $params,
            'not' => $not,
        ];

        return $this;
    }

    public function orWhereExists(string $sql, array $params = []): self
    {
        return $this->whereExists($sql, $params, false, 'or');
    }

    public function whereNotExists(string $sql, array $params = []): self
    {
        return $this->whereExists($sql, $params, true);
    }

    public function orWhereNotExists(string $sql, array $params = []): self
    {
        return $this->whereExists($sql, $params, true, 'or');
    }

    public function whereRaw(string $sql, array $params = [], string $boolean = 'and'): self
    {
        $sql = trim($sql);
        if ($sql === '') {
            return $this;
        }

        $boolean = strtolower($boolean) === 'or' ? 'or' : 'and';
        $this->wheres[] = [
            'type' => 'raw',
            'boolean' => $boolean,
            'sql' => $sql,
            'params' => $params,
        ];

        return $this;
    }

    public function orWhereRaw(string $sql, array $params = []): self
    {
        return $this->whereRaw($sql, $params, 'or');
    }

    public function withCount(array|string $relations): self
    {
        $items = is_string($relations) ? [$relations] : $relations;

        foreach ($items as $key => $value) {
            if (is_string($key) && is_callable($value)) {
                [$relation, $alias] = $this->parseRelationAlias($key);
                $this->withCount[] = [
                    'relation' => $relation,
                    'alias' => $this->formatCountAlias($relation, $alias),
                    'callback' => $value,
                ];
                continue;
            }
            if (is_string($value)) {
                [$relation, $alias] = $this->parseRelationAlias($value);
                $this->withCount[] = [
                    'relation' => $relation,
                    'alias' => $this->formatCountAlias($relation, $alias),
                    'callback' => null,
                ];
            }
        }

        return $this;
    }

    public function withSum(string $relation, string $column, ?callable $callback = null, ?string $alias = null): self
    {
        return $this->withAggregate($relation, $column, 'SUM', $callback, $alias);
    }

    public function withAvg(string $relation, string $column, ?callable $callback = null, ?string $alias = null): self
    {
        return $this->withAggregate($relation, $column, 'AVG', $callback, $alias);
    }

    public function withMin(string $relation, string $column, ?callable $callback = null, ?string $alias = null): self
    {
        return $this->withAggregate($relation, $column, 'MIN', $callback, $alias);
    }

    public function withMax(string $relation, string $column, ?callable $callback = null, ?string $alias = null): self
    {
        return $this->withAggregate($relation, $column, 'MAX', $callback, $alias);
    }

    public function withAggregate(string $relation, string $column, string $function, ?callable $callback = null, ?string $alias = null): self
    {
        [$relationName, $relationAlias] = $this->parseRelationAlias($relation);
        $aggregateAlias = $alias ?? $relationAlias ?? $this->sanitizeAlias($relationName . '_' . strtolower($function));

        $this->withAggregates[] = [
            'relation' => $relationName,
            'column' => $column,
            'function' => strtoupper($function),
            'alias' => $aggregateAlias,
            'callback' => $callback,
        ];

        return $this;
    }

    public function has(
        string $relation,
        string $operator = '>=',
        int $count = 1,
        ?callable $callback = null,
        string $boolean = 'and'
    ): self {
        $operator = $this->normalizeOperator($operator);
        $count = max(0, $count);

        if ($operator === '>=' && $count === 1) {
            [$sql, $params] = $this->compileRelationExists($relation, $callback);
            return $this->whereExists($sql, $params, false, $boolean);
        }

        [$sql, $params] = $this->compileRelationCount($relation, $callback);
        $params[] = $count;
        return $this->whereRaw('(' . $sql . ') ' . $operator . ' ?', $params, $boolean);
    }

    public function orHas(string $relation, string $operator = '>=', int $count = 1, ?callable $callback = null): self
    {
        return $this->has($relation, $operator, $count, $callback, 'or');
    }

    public function doesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, $callback);
    }

    public function orDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, $callback, 'or');
    }

    public function whereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        return $this->has($relation, $operator, $count, $callback, 'and');
    }

    public function orWhereHas(string $relation, ?callable $callback = null, string $operator = '>=', int $count = 1): self
    {
        return $this->has($relation, $operator, $count, $callback, 'or');
    }

    public function whereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, $callback, 'and');
    }

    public function orWhereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, $callback, 'or');
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    public function getWhereSql(): array
    {
        return $this->compileWhere();
    }

    public function addGlobalScope(callable $scope, ?string $name = null): self
    {
        $name = $name !== null && $name !== '' ? $name : 'scope_' . count($this->globalScopes);
        $this->globalScopes[] = [
            'name' => $name,
            'scope' => $scope,
        ];
        return $this;
    }

    public function withoutGlobalScope(string $name): self
    {
        if ($name !== '') {
            $this->disabledGlobalScopes[$name] = true;
        }
        return $this;
    }

    public function withoutGlobalScopes(?array $names = null): self
    {
        if ($names === null) {
            $this->applyGlobalScopes = false;
            return $this;
        }

        foreach ($names as $name) {
            if (is_string($name) && $name !== '') {
                $this->disabledGlobalScopes[$name] = true;
            }
        }

        return $this;
    }

    public function __call(string $method, array $args): mixed
    {
        $scope = 'scope' . ucfirst($method);
        if (class_exists($this->modelClass) && method_exists($this->modelClass, $scope)) {
            $model = new $this->modelClass();
            $result = $model->{$scope}($this, ...$args);
            return $result instanceof self || $result === null ? $this : $result;
        }

        throw new RuntimeException('Undefined query method: ' . $method);
    }

    private function newModelInstance(array $attributes): Model
    {
        $class = $this->modelClass;
        if (!class_exists($class)) {
            throw new RuntimeException('Model class not found: ' . $class);
        }
        if (!is_subclass_of($class, Model::class)) {
            throw new RuntimeException('Model class must extend ' . Model::class . ': ' . $class);
        }
        /** @var Model $model */
        $model = $class::fromDatabase($attributes);
        return $model;
    }

    private function eagerLoad(array $models, array $relations): array
    {
        if ($models === []) {
            return $models;
        }

        $sample = $models[0];
        if (!$sample instanceof Model) {
            return $models;
        }

        [$direct, $nested] = $this->splitRelations($relations);

        foreach ($direct as $name) {
            if (!method_exists($sample, $name)) {
                continue;
            }
            $relation = $sample->{$name}();
            if ($relation instanceof \Fnlla\Orm\Relations\Relation) {
                $relation->eagerLoad($models, $name);
            }
        }

        foreach ($nested as $parent => $children) {
            foreach ($models as $model) {
                if (!$model instanceof Model) {
                    continue;
                }
                $value = $model->getRelation($parent);
                if ($value instanceof Model) {
                    $value->load($children);
                    continue;
                }
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if ($item instanceof Model) {
                            $item->load($children);
                        }
                    }
                }
            }
        }

        return $models;
    }

    private function splitRelations(array $relations): array
    {
        $direct = [];
        $nested = [];
        foreach ($relations as $relation) {
            if (!is_string($relation) || $relation === '') {
                continue;
            }
            if (str_contains($relation, '.')) {
                [$parent, $child] = explode('.', $relation, 2);
                if ($parent !== '' && $child !== '') {
                    $direct[] = $parent;
                    $nested[$parent][] = $child;
                }
                continue;
            }
            $direct[] = $relation;
        }

        $direct = array_values(array_unique($direct));
        foreach ($nested as $parent => $children) {
            $nested[$parent] = array_values(array_unique($children));
        }

        return [$direct, $nested];
    }

    private function applyGlobalScopesIfNeeded(): void
    {
        if (!$this->applyGlobalScopes || $this->globalScopesApplied) {
            return;
        }
        foreach ($this->globalScopes as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if ($name !== '' && isset($this->disabledGlobalScopes[$name])) {
                continue;
            }
            $scope = $entry['scope'] ?? null;
            if (is_callable($scope)) {
                $scope($this);
            }
        }
        $this->globalScopesApplied = true;
    }

    private function compileRelationExists(string $relation, ?callable $callback): array
    {
        if (str_contains($relation, '.')) {
            [$first, $rest] = explode('.', $relation, 2);
            $nestedCallback = function (self $query) use ($rest, $callback): void {
                $query->whereHas($rest, $callback);
            };
            $relation = $first;
            $callback = $nestedCallback;
        }

        $model = $this->instantiateModel();
        if (!method_exists($model, $relation)) {
            throw new RuntimeException('Relation not found: ' . $relation);
        }
        $relationObject = $model->{$relation}();
        if (!$relationObject instanceof \Fnlla\Orm\Relations\Relation) {
            throw new RuntimeException('Relation method did not return a Relation: ' . $relation);
        }

        return $this->buildRelationExistsSql($relationObject, $callback);
    }

    private function compileRelationCount(string $relation, ?callable $callback): array
    {
        if (str_contains($relation, '.')) {
            [$first, $rest] = explode('.', $relation, 2);
            $nestedCallback = function (self $query) use ($rest, $callback): void {
                $query->whereHas($rest, $callback);
            };
            $relation = $first;
            $callback = $nestedCallback;
        }

        $model = $this->instantiateModel();
        if (!method_exists($model, $relation)) {
            throw new RuntimeException('Relation not found: ' . $relation);
        }
        $relationObject = $model->{$relation}();
        if (!$relationObject instanceof \Fnlla\Orm\Relations\Relation) {
            throw new RuntimeException('Relation method did not return a Relation: ' . $relation);
        }

        return $this->buildRelationCountSql($relationObject, $callback);
    }

    private function buildRelationExistsSql(\Fnlla\Orm\Relations\Relation $relation, ?callable $callback): array
    {
        $parentTable = $this->table;
        $relatedTable = method_exists($relation, 'getRelatedTable') ? $relation->getRelatedTable() : '';
        $relatedClass = $relation->getRelatedClass();

        $relatedBuilder = new self($this->pdo, $relatedTable, $relatedClass);
        if (class_exists($relatedClass)) {
            $instance = new $relatedClass();
            if ($instance instanceof \Fnlla\Orm\Model && method_exists($instance, 'usesSoftDeletes') && $instance->usesSoftDeletes()) {
                $relatedBuilder->setSoftDeleteColumn($instance->getDeletedAtColumn());
            }
        }
        if ($callback !== null) {
            $callback($relatedBuilder);
        }
        [$whereSql, $params] = $relatedBuilder->getWhereSql();

        if ($relation instanceof \Fnlla\Orm\Relations\HasMany || $relation instanceof \Fnlla\Orm\Relations\HasOne) {
            $foreignKey = $relation->getForeignKey();
            $localKey = $relation->getLocalKey();
            $sql = 'SELECT 1 FROM ' . $relatedTable . ' WHERE ' . $relatedTable . '.' . $foreignKey . ' = ' . $parentTable . '.' . $localKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        if ($relation instanceof \Fnlla\Orm\Relations\BelongsTo) {
            $ownerKey = $relation->getOwnerKey();
            $foreignKey = $relation->getForeignKey();
            $sql = 'SELECT 1 FROM ' . $relatedTable . ' WHERE ' . $relatedTable . '.' . $ownerKey . ' = ' . $parentTable . '.' . $foreignKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        if ($relation instanceof \Fnlla\Orm\Relations\BelongsToMany) {
            $pivot = $relation->getPivotTable();
            $foreignKey = $relation->getForeignKey();
            $relatedKey = $relation->getRelatedKey();
            $parentKey = $relation->getParentKey();
            $relatedPrimaryKey = $relation->getRelatedPrimaryKey();

            $sql = 'SELECT 1 FROM ' . $pivot . ' JOIN ' . $relatedTable . ' ON ' . $relatedTable . '.' . $relatedPrimaryKey . ' = ' . $pivot . '.' . $relatedKey;
            $sql .= ' WHERE ' . $pivot . '.' . $foreignKey . ' = ' . $parentTable . '.' . $parentKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        throw new RuntimeException('Unsupported relation type: ' . get_class($relation));
    }

    private function buildRelationCountSql(\Fnlla\Orm\Relations\Relation $relation, ?callable $callback): array
    {
        [$sql, $params] = $this->buildRelationExistsSql($relation, $callback);
        $sql = preg_replace('/^SELECT 1/', 'SELECT COUNT(*)', (string) $sql) ?? $sql;
        return [$sql, $params];
    }

    private function compileRelationAggregate(string $relation, string $column, string $function, ?callable $callback): array
    {
        if (str_contains($relation, '.')) {
            [$first, $rest] = explode('.', $relation, 2);
            $nestedCallback = function (self $query) use ($rest, $callback): void {
                $query->whereHas($rest, $callback);
            };
            $relation = $first;
            $callback = $nestedCallback;
        }

        $model = $this->instantiateModel();
        if (!method_exists($model, $relation)) {
            throw new RuntimeException('Relation not found: ' . $relation);
        }
        $relationObject = $model->{$relation}();
        if (!$relationObject instanceof \Fnlla\Orm\Relations\Relation) {
            throw new RuntimeException('Relation method did not return a Relation: ' . $relation);
        }

        return $this->buildRelationAggregateSql($relationObject, $column, $function, $callback);
    }

    private function buildRelationAggregateSql(
        \Fnlla\Orm\Relations\Relation $relation,
        string $column,
        string $function,
        ?callable $callback
    ): array {
        $parentTable = $this->table;
        $relatedTable = method_exists($relation, 'getRelatedTable') ? $relation->getRelatedTable() : '';
        $relatedClass = $relation->getRelatedClass();

        $relatedBuilder = new self($this->pdo, $relatedTable, $relatedClass);
        if (class_exists($relatedClass)) {
            $instance = new $relatedClass();
            if ($instance instanceof \Fnlla\Orm\Model && method_exists($instance, 'usesSoftDeletes') && $instance->usesSoftDeletes()) {
                $relatedBuilder->setSoftDeleteColumn($instance->getDeletedAtColumn());
            }
        }
        if ($callback !== null) {
            $callback($relatedBuilder);
        }
        [$whereSql, $params] = $relatedBuilder->getWhereSql();

        $columnRef = str_contains($column, '.') ? $column : ($relatedTable . '.' . $column);
        $function = strtoupper($function);

        if ($relation instanceof \Fnlla\Orm\Relations\HasMany || $relation instanceof \Fnlla\Orm\Relations\HasOne) {
            $foreignKey = $relation->getForeignKey();
            $localKey = $relation->getLocalKey();
            $sql = 'SELECT ' . $function . '(' . $columnRef . ') FROM ' . $relatedTable
                . ' WHERE ' . $relatedTable . '.' . $foreignKey . ' = ' . $parentTable . '.' . $localKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        if ($relation instanceof \Fnlla\Orm\Relations\BelongsTo) {
            $ownerKey = $relation->getOwnerKey();
            $foreignKey = $relation->getForeignKey();
            $sql = 'SELECT ' . $function . '(' . $columnRef . ') FROM ' . $relatedTable
                . ' WHERE ' . $relatedTable . '.' . $ownerKey . ' = ' . $parentTable . '.' . $foreignKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        if ($relation instanceof \Fnlla\Orm\Relations\BelongsToMany) {
            $pivot = $relation->getPivotTable();
            $foreignKey = $relation->getForeignKey();
            $relatedKey = $relation->getRelatedKey();
            $parentKey = $relation->getParentKey();
            $relatedPrimaryKey = $relation->getRelatedPrimaryKey();

            $sql = 'SELECT ' . $function . '(' . $columnRef . ') FROM ' . $pivot
                . ' JOIN ' . $relatedTable . ' ON ' . $relatedTable . '.' . $relatedPrimaryKey . ' = ' . $pivot . '.' . $relatedKey
                . ' WHERE ' . $pivot . '.' . $foreignKey . ' = ' . $parentTable . '.' . $parentKey;
            if ($whereSql !== '') {
                $sql .= ' AND ' . $whereSql;
            }
            return [$sql, $params];
        }

        throw new RuntimeException('Unsupported relation type: ' . get_class($relation));
    }

    private function instantiateModel(): Model
    {
        $class = $this->modelClass;
        if (!class_exists($class)) {
            throw new RuntimeException('Model class not found: ' . $class);
        }
        $instance = new $class();
        if (!$instance instanceof Model) {
            throw new RuntimeException('Model class must extend ' . Model::class . ': ' . $class);
        }
        return $instance;
    }

    private function parseRelationAlias(string $relation): array
    {
        $relation = trim($relation);
        if (preg_match('/^(.+)\s+as\s+(.+)$/i', $relation, $match) === 1) {
            return [trim($match[1]), trim($match[2])];
        }

        return [$relation, null];
    }

    private function formatCountAlias(string $relation, ?string $alias): string
    {
        if ($alias !== null && $alias !== '') {
            return $this->sanitizeAlias($alias);
        }
        return $this->sanitizeAlias($relation) . '_count';
    }

    private function sanitizeAlias(string $alias): string
    {
        $alias = str_replace(['.', '-'], '_', $alias);
        $alias = preg_replace('/[^A-Za-z0-9_]/', '_', $alias) ?? $alias;
        $alias = preg_replace('/_+/', '_', $alias) ?? $alias;
        return trim($alias, '_');
    }

    private function normalizeOperator(string $operator): string
    {
        $operator = trim($operator);
        if ($operator === '') {
            return '>=';
        }

        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<='];
        if (!in_array($operator, $allowed, true)) {
            throw new RuntimeException('Invalid operator: ' . $operator);
        }

        return $operator;
    }
}
