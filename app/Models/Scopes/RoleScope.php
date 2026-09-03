<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Roles are not quite like the other tenant-owned models, so they cannot use
 * TenantScope directly.
 *
 * The four system roles are the product's own vocabulary -- admin, agent,
 * team_lead, group_manager are referenced by name throughout the code -- so
 * they are shared, carry a null tenant_id, and must stay visible to everyone.
 * A role a tenant creates for itself belongs to that tenant and must not be.
 *
 * Plain TenantScope would hide the system roles, since null never equals a
 * tenant id, and every permission check in the app would start failing.
 */
class RoleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return;
        }

        $builder->where(function (Builder $query) use ($model, $tenant) {
            $query->whereNull($model->getTable().'.tenant_id')
                ->orWhere($model->getTable().'.tenant_id', $tenant->id);
        });
    }
}
