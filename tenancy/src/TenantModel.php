<?php
/**
 * fnlla
 * (c) TechAyo.co.uk
 * Proprietary License
 */
declare(strict_types=1);

namespace Fnlla\Tenancy;

use Fnlla\Orm\Model;
use Fnlla\Orm\QueryBuilder;
use RuntimeException;

abstract class TenantModel extends Model
{
    protected string $tenantColumn = 'tenant_id';
    protected bool $enforceTenant = true;

    public function save(): bool
    {
        if ($this->enforceTenant && $this->tenantColumn !== '') {
            $current = $this->getAttribute($this->tenantColumn);
            if ($current === null) {
                $tenantId = TenantContext::id();
                if ($tenantId !== null) {
                    $this->setAttribute($this->tenantColumn, $tenantId);
                }
            }
        }

        return parent::save();
    }

    protected function newQuery(): QueryBuilder
    {
        $builder = parent::newQuery();
        $tenantId = TenantContext::id();
        if ($this->enforceTenant && $this->tenantColumn !== '' && $tenantId !== null) {
            $builder->where($this->tenantColumn, $tenantId);
        }

        return $builder;
    }

    protected function relationQuery(string $related): QueryBuilder
    {
        if (!class_exists($related)) {
            throw new RuntimeException('Related model not found: ' . $related);
        }

        /** @var Model $relatedInstance */
        $relatedInstance = new $related();
        if ($relatedInstance instanceof self) {
            return $relatedInstance->newQuery();
        }

        return new QueryBuilder($this->connection(), $relatedInstance->getTable(), $related);
    }
}
