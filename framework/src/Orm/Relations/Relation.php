<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm\Relations;

use Fnlla\Orm\Model;
use Fnlla\Orm\QueryBuilder;

abstract class Relation
{
    public function __construct(
        protected Model $parent,
        protected string $relatedClass,
        protected QueryBuilder $builder
    )
    {
    }

    public function get(): array
    {
        return $this->builder->get();
    }

    public function first(): ?Model
    {
        return $this->builder->first();
    }

    public function builder(): QueryBuilder
    {
        return $this->builder;
    }

    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    public function __call(string $method, array $args): mixed
    {
        $result = $this->builder->{$method}(...$args);
        return $result instanceof QueryBuilder ? $this : $result;
    }

    abstract public function eagerLoad(array $models, string $relationName): void;

    abstract public function getResults(): mixed;
}
