<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

final class Table
{
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];

    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function columns(): array
    {
        return $this->columns;
    }

    public function indexes(): array
    {
        return $this->indexes;
    }

    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        return $this->addColumn($name, 'id')->primary()->autoIncrement();
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        return $this->addColumn($name, 'string', $length);
    }

    public function text(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'text');
    }

    public function integer(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'integer');
    }

    public function boolean(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'boolean');
    }

    public function datetime(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'datetime');
    }

    public function timestamps(string $createdAt = 'created_at', string $updatedAt = 'updated_at'): void
    {
        $this->datetime($createdAt)->nullable();
        $this->datetime($updatedAt)->nullable();
    }

    public function json(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'json');
    }

    public function foreignId(string $name): ColumnDefinition
    {
        return $this->addColumn($name, 'foreignId');
    }

    public function index(array|string $columns, ?string $name = null): void
    {
        $cols = $this->normaliseColumns($columns);
        $this->indexes[] = [
            'type' => 'index',
            'columns' => $cols,
            'name' => $name,
        ];
    }

    public function unique(array|string $columns, ?string $name = null): void
    {
        $cols = $this->normaliseColumns($columns);
        $this->indexes[] = [
            'type' => 'unique',
            'columns' => $cols,
            'name' => $name,
        ];
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $this->foreignKeys[] = [
            'column' => $column,
            'references_table' => null,
            'references_column' => 'id',
            'on_delete' => null,
            'on_update' => null,
        ];
        $index = array_key_last($this->foreignKeys);
        return new ForeignKeyDefinition($this, (int) $index);
    }

    public function getForeignKey(int $index): array
    {
        return $this->foreignKeys[$index] ?? [];
    }

    public function updateForeignKey(int $index, array $data): void
    {
        $this->foreignKeys[$index] = $data;
    }

    private function addColumn(string $name, string $type, ?int $length = null): ColumnDefinition
    {
        $column = new ColumnDefinition($this, $name, $type, $length);
        $this->columns[] = $column;
        return $column;
    }

    private function normaliseColumns(array|string $columns): array
    {
        $cols = is_array($columns) ? $columns : [$columns];
        $cols = array_values(array_filter(array_map('strval', $cols), fn ($item) => $item !== ''));
        return $cols;
    }
}
