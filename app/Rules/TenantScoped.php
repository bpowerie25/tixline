<?php

namespace App\Rules;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TenantScoped
{
    /**
     * An `exists` rule confined to the tenant handling the request.
     *
     * `exists:` compiles straight to a query builder call, so the Eloquent
     * TenantScope that guards every read never applies to it. Every endpoint
     * validating a foreign id therefore accepted ids belonging to other
     * tenants: assigning a ticket to another tenant's agent succeeded, and the
     * response came back with that agent's name on it.
     *
     * With no tenant bound — a single-tenant install, or the console — this is
     * exactly a plain exists rule.
     */
    public static function exists(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column);

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant) {
            $rule->where('tenant_id', $tenant->id);
        }

        return $rule;
    }
}
