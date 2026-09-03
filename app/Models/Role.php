<?php

namespace App\Models;

use App\Models\Scopes\RoleScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    const ADMIN = 'admin';

    const GROUP_MANAGER = 'group_manager';

    const TEAM_LEAD = 'team_lead';

    const AGENT = 'agent';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
    ];

    /**
     * System roles are shared and keep a null tenant_id; a role a tenant
     * creates belongs to that tenant. See RoleScope.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new RoleScope);

        static::creating(function (Role $role) {
            if ($role->tenant_id || $role->is_system) {
                return;
            }

            $tenant = app()->bound('tenant') ? app('tenant') : null;

            if ($tenant) {
                $role->tenant_id = $tenant->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Whether this role's definition may be changed from inside a tenant.
     * The system roles are shared rows, so editing one in a hosted install
     * rewrites permissions for every other customer.
     */
    public function isEditableByTenant(): bool
    {
        return ! ($this->is_system && config('support.multi_tenant'));
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
