<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm\Relations;

use Fnlla\Orm\Model;

final class BelongsTo extends Relation
{
    public function __construct(
        Model $parent,
        string $relatedClass,
        private string $foreignKey,
        private string $ownerKey,
        \Fnlla\Orm\QueryBuilder $builder
    )
    {
        parent::__construct($parent, $relatedClass, $builder);
    }

    public function getResults(): ?Model
    {
        return $this->first();
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    public function getRelatedTable(): string
    {
        $class = $this->relatedClass;
        /** @var Model $instance */
        $instance = new $class();
        return $instance->getTable();
    }

    public function associate(Model $model): Model
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->ownerKey));
        return $this->parent;
    }

    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        return $this->parent;
    }

    public function eagerLoad(array $models, string $relationName): void
    {
        $keys = [];
        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $value = $model->getAttribute($this->foreignKey);
            if ($value !== null) {
                $keys[] = $value;
            }
        }
        $keys = array_values(array_unique($keys));

        $related = [];
        if ($keys !== []) {
            $rows = $this->relatedClass::query()->whereIn($this->ownerKey, $keys)->get();
            foreach ($rows as $row) {
                if ($row instanceof Model) {
                    $related[$row->getAttribute($this->ownerKey)] = $row;
                }
            }
        }

        foreach ($models as $model) {
            if (!$model instanceof Model) {
                continue;
            }
            $value = $model->getAttribute($this->foreignKey);
            $model->setRelation($relationName, $value !== null ? ($related[$value] ?? null) : null);
        }
    }
}
