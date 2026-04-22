<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm;

use RuntimeException;

abstract class Factory
{
    protected array $state = [];
    protected static string $model = '';

    public static function new(array $state = []): static
    {
        $factory = new static();
        $factory->state = $state;
        return $factory;
    }

    public function state(array $attributes): static
    {
        $this->state = array_merge($this->state, $attributes);
        return $this;
    }

    abstract public function definition(): array;

    public function make(array $overrides = []): Model
    {
        $modelClass = static::$model;
        if ($modelClass === '') {
            throw new RuntimeException('Factory model not configured.');
        }

        $data = array_merge($this->definition(), $this->state, $overrides);
        $model = new $modelClass($data);
        if (!$model instanceof Model) {
            throw new RuntimeException('Factory model must extend ' . Model::class . '.');
        }

        return $model;
    }

    public function create(array $overrides = []): Model
    {
        $model = $this->make($overrides);
        $model->save();
        return $model;
    }
}
