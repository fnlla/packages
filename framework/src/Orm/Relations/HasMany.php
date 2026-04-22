<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm\Relations;

use Fnlla\Orm\Model;

final class HasMany extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private string $foreignKey,
        private string $localKey,
        \Fnlla\Orm\QueryBuilder $builder
    )
    {
        parent::__construct($parent, $relatedClass, $builder);
    }

    public function getResults(): array
    {
        return $this->get();
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    public function getRelatedTable(): string
    {
        $class = $this->relatedClass;
        /** @var Model $instance */
        $instance = new $class();
        return $instance->getTable();
    }

    public function make(array $attributes = []): Model
    {
        $class = $this->relatedClass;
        /** @var Model $model */
        $model = new $class($attributes);
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));
        return $model;
    }

    public function create(array $attributes = []): Model
    {
        $model = $this->make($attributes);
        $model->save();
        return $model;
    }

    public function save(Model $model): bool
    {
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->localKey));
        return $model->save();
    }

    public function saveMany(array $models): int
    {
        $count = 0;
        foreach ($models as $model) {
            if ($model instanceof Model && $this->save($model)) {
                $count++;
            }
        }
        return $count;
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $keys = [];
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $value = $model->getAttribute($this->localKey);
            if ($value !== null) {
                $keys[] = $value;
            }
        }
        $keys = array_values(array_unique($keys));

        $grouped = [];
        if ($keys !== []) {
            $rows = $this->relatedClass::query()->whereIn($this->foreignKey, $keys)->get();
            foreach ($rows as $row) {
                if (!$row instanceof Model) {
                    continue;
                }
                $key = $row->getAttribute($this->foreignKey);
                if ($key === null) {
                    continue;
                }
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $row;
            }
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $value = $model->getAttribute($this->localKey);
            $model->setRelation($relationName, $value !== null ? ($grouped[$value] ?? []) : []);
        }
    }
}
