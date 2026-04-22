<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Database;

final class ForeignKeyDefinition
{
    public function __construct(private Table $table, private int $index)
    {
    }

    public function references(string $table, string $column = 'id'): self
    {
        $data = $this->table->getForeignKey($this->index);
        $data['references_table'] = $table;
        $data['references_column'] = $column;
        $this->table->updateForeignKey($this->index, $data);
        return $this;
    }

    public function onDelete(string $action): self
    {
        $data = $this->table->getForeignKey($this->index);
        $data['on_delete'] = strtoupper($action);
        $this->table->updateForeignKey($this->index, $data);
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $data = $this->table->getForeignKey($this->index);
        $data['on_update'] = strtoupper($action);
        $this->table->updateForeignKey($this->index, $data);
        return $this;
    }
}
