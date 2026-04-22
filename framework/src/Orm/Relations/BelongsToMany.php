<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm\Relations;

use Fnlla\Database\Query as BaseQuery;
use Fnlla\Orm\Model;

final class BelongsToMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private string $pivotTable,
        private string $foreignKey,
        private string $relatedKey,
        private string $parentKey,
        private string $relatedPrimaryKey,
        \Fnlla\Orm\QueryBuilder $builder,
        private \PDO $pdo
    )
    {
        parent::__construct($parent, $relatedClass, $builder);
    }

    private array $pivotColumns = [];
    private array $pivotTimestamps = [];

    public function getResults(): array
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        if ($parentId === null) {
            return [];
        }

        $map = $this->loadPivotRows([$parentId]);
        $ids = array_keys($map[$parentId] ?? []);
        if ($ids === []) {
            return [];
        }

        $rows = $this->relatedClass::query()->whereIn($this->relatedPrimaryKey, $ids)->get();
        foreach ($rows as $row) {
            if (!$row instanceof Model) {
                continue;
            }
            $id = $row->getAttribute($this->relatedPrimaryKey);
            if ($id !== null && isset($map[$parentId][$id])) {
                $row->setRelation('pivot', $map[$parentId][$id]);
            }
        }

        return $rows;
    }

    public function get(): array
    {
        return $this->getResults();
    }

    public function first(): ?Model
    {
        $items = $this->getResults();
        return $items[0] ?? null;
    }

    public function withPivot(array $columns): self
    {
        $this->pivotColumns = $columns;
        return $this;
    }

    public function withTimestamps(string $createdAt = 'created_at', string $updatedAt = 'updated_at'): self
    {
        $this->pivotTimestamps = array_values(array_unique(array_filter([$createdAt, $updatedAt])));
        if ($this->pivotTimestamps !== []) {
            $this->pivotColumns = array_values(array_unique(array_merge($this->pivotColumns, $this->pivotTimestamps)));
        }
        return $this;
    }

    public function getPivotTable(): string
    {
        return $this->pivotTable;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    public function getParentKey(): string
    {
        return $this->parentKey;
    }

    public function getRelatedPrimaryKey(): string
    {
        return $this->relatedPrimaryKey;
    }

    public function getRelatedTable(): string
    {
        $class = $this->relatedClass;
        /** @var Model $instance */
        $instance = new $class();
        return $instance->getTable();
    }

    public function attach(int|string|array $ids, array $attributes = []): int
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        if ($parentId === null) {
            return 0;
        }

        $ids = is_array($ids) ? $ids : [$ids];
        $map = $this->normalizePivotMap($ids);
        $inserted = 0;
        foreach ($map as $id => $pivotAttributes) {
            $values = array_merge($attributes, $pivotAttributes, [
                $this->foreignKey => $parentId,
                $this->relatedKey => $id,
            ]);
            $this->applyPivotTimestamps($values);
            $query = new BaseQuery($this->pdo);
            $query->table($this->pivotTable)->insert($values);
            $inserted++;
        }

        return $inserted;
    }

    public function detach(int|string|array|null $ids = null): int
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        if ($parentId === null) {
            return 0;
        }

        $query = new BaseQuery($this->pdo);
        $query->table($this->pivotTable)->where($this->foreignKey, $parentId);
        if ($ids !== null) {
            $ids = is_array($ids) ? $ids : [$ids];
            $query->whereIn($this->relatedKey, $ids);
        }

        return $query->delete();
    }

    public function sync(array $ids): array
    {
        $parentId = $this->parent->getAttribute($this->parentKey);
        if ($parentId === null) {
            return ['attached' => [], 'detached' => []];
        }

        $map = $this->normalizePivotMap($ids);
        $ids = array_values(array_unique(array_keys($map)));
        $current = $this->loadPivotMap([$parentId]);
        $currentIds = $current[$parentId] ?? [];

        $toAttach = array_values(array_diff($ids, $currentIds));
        $toDetach = array_values(array_diff($currentIds, $ids));

        if ($toDetach !== []) {
            $this->detach($toDetach);
        }
        if ($toAttach !== []) {
            $withAttributes = [];
            foreach ($toAttach as $id) {
                $withAttributes[$id] = $map[$id] ?? [];
            }
            $this->attach($withAttributes);
        }

        return ['attached' => $toAttach, 'detached' => $toDetach];
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $keys = [];
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $value = $model->getAttribute($this->parentKey);
            if ($value !== null) {
                $keys[] = $value;
            }
        }
        $keys = array_values(array_unique($keys));

        $map = $keys !== [] ? $this->loadPivotRows($keys) : [];

        $relatedIds = [];
        foreach ($map as $list) {
            foreach (array_keys($list) as $id) {
                $relatedIds[] = $id;
            }
        }
        $relatedIds = array_values(array_unique($relatedIds));

        $relatedIndex = [];
        if ($relatedIds !== []) {
            $rows = $this->relatedClass::query()->whereIn($this->relatedPrimaryKey, $relatedIds)->get();
            foreach ($rows as $row) {
                if (!$row instanceof Model) {
                    continue;
                }
                $id = $row->getAttribute($this->relatedPrimaryKey);
                if ($id !== null) {
                    $relatedIndex[$id] = $row;
                }
            }
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $parentId = $model->getAttribute($this->parentKey);
            $items = [];
            if ($parentId !== null) {
                $ids = $map[$parentId] ?? [];
                foreach ($ids as $id => $pivotRow) {
                    if (isset($relatedIndex[$id])) {
                        $related = $relatedIndex[$id];
                        if ($related instanceof Model) {
                            $related->setRelation('pivot', $pivotRow);
                        }
                        $items[] = $related;
                    }
                }
            }
            $model->setRelation($relationName, $items);
        }
    }

    private function loadPivotRows(array $parentIds): array
    {
        $query = new BaseQuery($this->pdo);
        $columns = ['*'];
        if ($this->pivotColumns !== []) {
            $columns = array_values(array_unique(array_merge(
                [$this->foreignKey, $this->relatedKey],
                $this->pivotColumns
            )));
        }

        $rows = $query->table($this->pivotTable)->select($columns)->whereIn($this->foreignKey, $parentIds)->get();

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $parentId = $row[$this->foreignKey] ?? null;
            $relatedId = $row[$this->relatedKey] ?? null;
            if ($parentId === null || $relatedId === null) {
                continue;
            }
            if (!isset($map[$parentId])) {
                $map[$parentId] = [];
            }
            $map[$parentId][$relatedId] = $row;
        }

        return $map;
    }

    private function loadPivotMap(array $parentIds): array
    {
        $rows = $this->loadPivotRows($parentIds);
        $map = [];
        foreach ($rows as $parentId => $items) {
            $map[$parentId] = array_keys($items);
        }
        return $map;
    }

    private function normalizePivotMap(array $ids): array
    {
        $map = [];
        foreach ($ids as $key => $value) {
            if (is_array($value)) {
                $id = $key;
                $attrs = $value;
            } else {
                $id = $value;
                $attrs = [];
            }

            if (!is_int($id) && !is_string($id)) {
                continue;
            }

            $map[$id] = $attrs;
        }

        return $map;
    }

    private function applyPivotTimestamps(array &$values): void
    {
        if ($this->pivotTimestamps === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($this->pivotTimestamps as $column) {
            if (!array_key_exists($column, $values)) {
                $values[$column] = $now;
            }
        }
    }
}
