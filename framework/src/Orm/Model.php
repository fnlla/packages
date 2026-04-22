<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Orm;

use DateTimeImmutable;
use DateTimeInterface;
use Fnlla\Core\Container;
use Fnlla\Database\ConnectionManager;
use Fnlla\Database\Query;
use RuntimeException;
use Fnlla\Orm\Relations\BelongsTo;
use Fnlla\Orm\Relations\HasMany;
use Fnlla\Orm\Relations\HasOne;
use Fnlla\Orm\Relations\Relation;
use Fnlla\Orm\Relations\BelongsToMany;

abstract class Model
{
    protected static ?ConnectionManager $connectionManager = null;
    protected static ?Container $container = null;
    protected static array $globalScopes = [];

    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $timestamps = true;
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';
    protected string $deletedAt = 'deleted_at';
    protected array $fillable = [];
    protected array $guarded = [];
    protected array $casts = [];

    protected array $attributes = [];
    protected array $relations = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        if ($attributes !== []) {
            $this->fill($attributes);
        }
    }

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            $relation = $this->{$key}();
            if ($relation instanceof Relation) {
                $value = $relation->getResults();
                $this->setRelation($key, $value);
                return $value;
            }
        }

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public static function __callStatic(string $method, array $args): mixed
    {
        $builder = static::query();
        return $builder->{$method}(...$args);
    }

    public static function setConnectionManager(ConnectionManager $manager): void
    {
        static::$connectionManager = $manager;
    }

    public static function setContainer(Container $container): void
    {
        static::$container = $container;
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        $builder = $instance->newQuery();
        if ($instance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($instance->getDeletedAtColumn());
        }
        $instance->applyGlobalScopes($builder);
        return $builder;
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function withTrashed(): QueryBuilder
    {
        $instance = new static();
        $builder = $instance->newQuery();
        if ($instance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($instance->getDeletedAtColumn())->withTrashed();
        }
        $instance->applyGlobalScopes($builder);
        return $builder;
    }

    public static function onlyTrashed(): QueryBuilder
    {
        $instance = new static();
        $builder = $instance->newQuery();
        if ($instance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($instance->getDeletedAtColumn())->onlyTrashed();
        }
        $instance->applyGlobalScopes($builder);
        return $builder;
    }

    public static function withoutGlobalScopes(?array $names = null): QueryBuilder
    {
        $instance = new static();
        $builder = $instance->newQuery();
        if ($instance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($instance->getDeletedAtColumn());
        }
        $builder->withoutGlobalScopes($names);
        return $builder;
    }

    public static function withoutGlobalScope(string $name): QueryBuilder
    {
        $instance = new static();
        $builder = $instance->newQuery();
        if ($instance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($instance->getDeletedAtColumn());
        }
        $builder->withoutGlobalScope($name);
        return $builder;
    }

    public static function addGlobalScope(callable|string $scope, ?string $name = null): void
    {
        if ($name !== null && $name !== '') {
            static::$globalScopes[$name] = $scope;
            return;
        }

        static::$globalScopes[] = $scope;
    }

    public static function find(mixed $id): ?static
    {
        $primaryKey = (new static())->getKeyName();
        $result = static::query()->where($primaryKey, $id)->first();
        return $result instanceof static ? $result : null;
    }

    public static function findOrFail(mixed $id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new ModelNotFoundException(static::class . ' not found.');
        }
        return $model;
    }

    public static function firstWhere(string $column, mixed $operatorOrValue, mixed $value = null): ?static
    {
        $result = static::query()->where($column, $operatorOrValue, $value)->first();
        return $result instanceof static ? $result : null;
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function save(): bool
    {
        $pdo = $this->connection();
        $table = $this->getTable();
        $primaryKey = $this->getKeyName();

        if ($this->timestamps) {
            $now = $this->formatTimestamp(new DateTimeImmutable());
            $this->attributes[$this->updatedAt] = $now;
            if (!$this->exists) {
                $this->attributes[$this->createdAt] = $now;
            }
        }

        $query = new Query($pdo);
        $values = $this->serialisedAttributes();

        if ($this->exists && isset($values[$primaryKey])) {
            $id = $values[$primaryKey];
            unset($values[$primaryKey]);
            if ($values === []) {
                return true;
            }
            $query->table($table)->where($primaryKey, $id)->update($values);
            return true;
        }

        $query->table($table)->insert($values);
        if (!isset($this->attributes[$primaryKey])) {
            $id = $pdo->lastInsertId();
            if ($id !== false && $id !== '') {
                $this->attributes[$primaryKey] = is_numeric($id) ? (int) $id : $id;
            }
        }

        $this->exists = true;
        return true;
    }

    public function delete(): bool
    {
        if ($this->usesSoftDeletes() && method_exists($this, 'softDelete')) {
            return $this->softDelete();
        }

        return $this->performDelete();
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable((string) $key)) {
                $this->setAttribute((string) $key, $value);
            }
        }
        return $this;
    }

    public function forceFill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute((string) $key, $value);
        }
        return $this;
    }

    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            if ($this->hasGetMutator($key)) {
                return $this->getMutatedAttribute($key, null);
            }
            return null;
        }

        $value = $this->attributes[$key];
        $cast = $this->casts[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $value = $this->castAttribute($value, $cast);

        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttribute($key, $value);
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->hasSetMutator($key)) {
            $mutated = $this->setMutatedAttribute($key, $value);
            if (is_array($mutated)) {
                foreach ($mutated as $attr => $attrValue) {
                    $this->attributes[$attr] = $attrValue;
                }
                return;
            }
            $value = $mutated;
        }

        $cast = $this->casts[$key] ?? null;
        if ($cast === 'datetime' && $value instanceof DateTimeInterface) {
            $value = $this->formatTimestamp($value);
        }

        $this->attributes[$key] = $value;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function increment(string $column, int $amount = 1): bool
    {
        $current = (int) ($this->getAttribute($column) ?? 0);
        $this->setAttribute($column, $current + $amount);
        return $this->save();
    }

    public function decrement(string $column, int $amount = 1): bool
    {
        $current = (int) ($this->getAttribute($column) ?? 0);
        $this->setAttribute($column, $current - $amount);
        return $this->save();
    }

    public function refresh(): self
    {
        $primaryKey = $this->getKeyName();
        if (!isset($this->attributes[$primaryKey])) {
            return $this;
        }

        $fresh = static::query()->where($primaryKey, $this->attributes[$primaryKey])->first();
        if ($fresh instanceof static) {
            $this->setRawAttributes($fresh->getAttributes(), true);
            $this->relations = [];
        }

        return $this;
    }

    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $builder = static::query();
        foreach ($attributes as $key => $value) {
            $builder->where((string) $key, $value);
        }
        $model = $builder->first();
        if ($model instanceof static) {
            return $model;
        }

        return new static(array_merge($attributes, $values));
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes, $values);
        if (!$model->exists()) {
            $model->save();
        }
        return $model;
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes);
        if ($model->exists()) {
            $model->update($values);
            return $model;
        }

        $model->fill($values);
        $model->save();
        return $model;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getTable(): string
    {
        if ($this->table !== '') {
            return $this->table;
        }

        $class = static::class;
        $base = substr($class, strrpos($class, '\\') !== false ? strrpos($class, '\\') + 1 : 0);
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $base) ?? $base);
        return $this->pluralise($snake);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function toArray(): array
    {
        $data = [];
        foreach (array_keys($this->attributes) as $key) {
            $data[$key] = $this->getAttribute($key);
        }
        foreach ($this->relations as $name => $value) {
            $data[$name] = $value;
        }
        return $data;
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    public function load(string|array $relations): self
    {
        $items = is_array($relations) ? $relations : [$relations];
        [$direct, $nested] = $this->splitRelations($items);

        foreach ($direct as $relation) {
            if (is_string($relation) && method_exists($this, $relation)) {
                $rel = $this->{$relation}();
                if ($rel instanceof Relation) {
                    $this->setRelation($relation, $rel->getResults());
                }
            }
        }

        foreach ($nested as $parent => $children) {
            $value = $this->getRelation($parent);
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

        return $this;
    }

    public static function fromDatabase(array $attributes): static
    {
        $model = new static();
        $model->forceFill($attributes);
        $model->exists = true;
        return $model;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setRawAttributes(array $attributes, bool $exists = false): self
    {
        $this->attributes = $attributes;
        $this->exists = $exists;
        return $this;
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, string $ownerKey = 'id'): BelongsTo
    {
        $foreignKey ??= $this->snake(class_basename($related)) . '_id';
        $builder = $this->relationQuery($related)->where($ownerKey, $this->getAttribute($foreignKey));
        return new BelongsTo($this, $related, $foreignKey, $ownerKey, $builder);
    }

    protected function hasMany(string $related, ?string $foreignKey = null, string $localKey = 'id'): HasMany
    {
        $foreignKey ??= $this->snake(class_basename(static::class)) . '_id';
        $builder = $this->relationQuery($related)->where($foreignKey, $this->getAttribute($localKey));
        return new HasMany($this, $related, $foreignKey, $localKey, $builder);
    }

    protected function hasOne(string $related, ?string $foreignKey = null, string $localKey = 'id'): HasOne
    {
        $foreignKey ??= $this->snake(class_basename(static::class)) . '_id';
        $builder = $this->relationQuery($related)->where($foreignKey, $this->getAttribute($localKey));
        return new HasOne($this, $related, $foreignKey, $localKey, $builder);
    }

    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignKey = null,
        ?string $relatedKey = null,
        string $parentKey = 'id',
        string $relatedPrimaryKey = 'id'
    ): BelongsToMany {
        $parentName = $this->snake(class_basename(static::class));
        $relatedName = $this->snake(class_basename($related));
        if ($pivotTable === null) {
            $parts = [$parentName, $relatedName];
            sort($parts);
            $pivotTable = $parts[0] . '_' . $parts[1];
        }
        $foreignKey ??= $parentName . '_id';
        $relatedKey ??= $relatedName . '_id';

        $builder = $this->relationQuery($related);
        return new BelongsToMany(
            $this,
            $related,
            $pivotTable,
            $foreignKey,
            $relatedKey,
            $parentKey,
            $relatedPrimaryKey,
            $builder,
            $this->connection()
        );
    }

    protected function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->connection(), $this->getTable(), static::class);
    }

    protected function relationQuery(string $related): QueryBuilder
    {
        if (!class_exists($related)) {
            throw new RuntimeException('Related model not found: ' . $related);
        }

        /** @var Model $relatedInstance */
        $relatedInstance = new $related();
        $builder = new QueryBuilder($this->connection(), $relatedInstance->getTable(), $related);
        if ($relatedInstance instanceof Model && $relatedInstance->usesSoftDeletes()) {
            $builder->setSoftDeleteColumn($relatedInstance->getDeletedAtColumn());
        }
        $relatedInstance->applyGlobalScopes($builder);
        return $builder;
    }

    protected function connection(): \PDO
    {
        $manager = $this->resolveConnectionManager();
        return $manager->connection();
    }

    protected function resolveConnectionManager(): ConnectionManager
    {
        if (static::$connectionManager instanceof ConnectionManager) {
            return static::$connectionManager;
        }

        if (static::$container instanceof Container && static::$container->has(ConnectionManager::class)) {
            $manager = static::$container->make(ConnectionManager::class);
            if ($manager instanceof ConnectionManager) {
                static::$connectionManager = $manager;
                return $manager;
            }
        }

        throw new RuntimeException('ConnectionManager not configured. Ensure the core Database module is configured and the provider is booted.');
    }

    protected function isFillable(string $key): bool
    {
        if ($this->fillable !== []) {
            return in_array($key, $this->fillable, true);
        }

        if ($this->guarded === ['*']) {
            return false;
        }

        return !in_array($key, $this->guarded, true);
    }

    public function usesSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses($this), true);
    }

    public function getDeletedAtColumn(): string
    {
        return $this->deletedAt !== '' ? $this->deletedAt : 'deleted_at';
    }

    protected function castDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return new DateTimeImmutable($value->format('Y-m-d H:i:s'));
        }
        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }

    protected function formatTimestamp(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    protected function performDelete(): bool
    {
        $primaryKey = $this->getKeyName();
        if (!isset($this->attributes[$primaryKey])) {
            return false;
        }

        $pdo = $this->connection();
        $query = new Query($pdo);
        $query->table($this->getTable())->where($primaryKey, $this->attributes[$primaryKey])->delete();
        $this->exists = false;
        return true;
    }

    protected function serialisedAttributes(): array
    {
        $values = [];
        foreach ($this->attributes as $key => $value) {
            $cast = $this->casts[$key] ?? null;
            if ($cast === 'datetime' && $value instanceof DateTimeInterface) {
                $values[$key] = $this->formatTimestamp($value);
                continue;
            }
            if (in_array($cast, ['array', 'json'], true) && is_array($value)) {
                $values[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                continue;
            }
            $values[$key] = $value;
        }
        return $values;
    }

    protected function castAttribute(mixed $value, ?string $cast): mixed
    {
        if ($value === null || $cast === null || $cast === '') {
            return $value;
        }

        return match ($cast) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'datetime' => $this->castDateTime($value),
            'array', 'json' => is_string($value) ? (json_decode($value, true) ?: []) : (array) $value,
            default => $value,
        };
    }

    protected function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . $this->studly($key) . 'Attribute');
    }

    protected function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . $this->studly($key) . 'Attribute');
    }

    protected function getMutatedAttribute(string $key, mixed $value): mixed
    {
        $method = 'get' . $this->studly($key) . 'Attribute';
        return $this->{$method}($value);
    }

    protected function setMutatedAttribute(string $key, mixed $value): mixed
    {
        $method = 'set' . $this->studly($key) . 'Attribute';
        return $this->{$method}($value);
    }

    protected function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    protected function snake(string $value): string
    {
        $value = preg_replace('/([a-z])([A-Z])/', '$1_$2', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/_+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    protected function pluralise(string $value): string
    {
        $lower = strtolower($value);
        if (str_ends_with($lower, 'y') && !preg_match('/[aeiou]y$/', $lower)) {
            return substr($value, 0, -1) . 'ies';
        }
        if (preg_match('/(s|x|z|ch|sh)$/', $lower)) {
            return $value . 'es';
        }
        return $value . 's';
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

    protected function applyGlobalScopes(QueryBuilder $builder): void
    {
        foreach (static::$globalScopes as $name => $scope) {
            $scopeName = is_int($name)
                ? (is_string($scope) ? $scope : ('scope_' . $name))
                : (string) $name;

            if (is_callable($scope)) {
                $builder->addGlobalScope($scope, $scopeName);
                continue;
            }
            if (is_string($scope) && method_exists($this, $scope)) {
                $builder->addGlobalScope(function (QueryBuilder $query) use ($scope): void {
                    $this->{$scope}($query);
                }, $scopeName);
            }
        }
    }
}

function class_basename(string $class): string
{
    $class = trim($class, '\\');
    $pos = strrpos($class, '\\');
    return $pos === false ? $class : substr($class, $pos + 1);
}
