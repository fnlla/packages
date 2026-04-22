<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

final class ColumnDefinition
{
    public function __construct(
        private Table $table,
        private string $name,
        private string $type,
        private ?int $length = null
    )
    {
    }

    private bool $nullable = false;
    private mixed $default = null;
    private bool $hasDefault = false;
    private bool $primary = false;
    private bool $autoIncrement = false;

    public function nullable(bool $state = true): self
    {
        $this->nullable = $state;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function primary(bool $state = true): self
    {
        $this->primary = $state;
        return $this;
    }

    public function autoIncrement(bool $state = true): self
    {
        $this->autoIncrement = $state;
        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->table->unique($this->name, $name);
        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->table->index($this->name, $name);
        return $this;
    }

    public function references(string $table, string $column = 'id'): ForeignKeyDefinition
    {
        return $this->table->foreign($this->name)->references($table, $column);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }
}
