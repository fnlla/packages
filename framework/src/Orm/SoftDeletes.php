<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */

declare(strict_types=1);

namespace Fnlla\Orm;

use DateTimeImmutable;

trait SoftDeletes
{
    public function trashed(): bool
    {
        $column = $this->getDeletedAtColumn();
        $value = $this->getAttribute($column);
        return $value !== null && $value !== '';
    }

    public function softDelete(): bool
    {
        $column = $this->getDeletedAtColumn();
        $now = $this->formatTimestamp(new DateTimeImmutable());
        $this->setAttribute($column, $now);

        return $this->persistSoftDelete([$column => $now]);
    }

    public function restore(): bool
    {
        $column = $this->getDeletedAtColumn();
        $this->setAttribute($column, null);
        return $this->persistSoftDelete([$column => null]);
    }

    public function forceDelete(): bool
    {
        return $this->performDelete();
    }

    private function persistSoftDelete(array $values): bool
    {
        $primaryKey = $this->getKeyName();
        if (!isset($this->attributes[$primaryKey])) {
            return false;
        }

        $pdo = $this->connection();
        $query = new \Fnlla\Database\Query($pdo);
        $query->table($this->getTable())->where($primaryKey, $this->attributes[$primaryKey])->update($values);
        return true;
    }
}
