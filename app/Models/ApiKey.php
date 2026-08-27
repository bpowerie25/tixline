<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A Sanctum personal access token, presented to agents as an API key.
 *
 * Sanctum's own model is extended rather than replaced so token lookup and
 * ability checks keep working unchanged; this adds the tenant the key belongs
 * to, so keys can be listed and revoked per tenant.
 */
class ApiKey extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name', 'token', 'abilities', 'expires_at', 'tenant_id',
    ];

    /**
     * Sanctum declares its casts as a property, so they are merged here rather
     * than replaced. tenant_id is cast so ownership checks can compare strictly.
     */
    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'tenant_id' => 'integer',
    ];

    /**
     * The abilities a key may be granted, and what each one unlocks.
     */
    public const ABILITIES = [
        'tickets:read' => 'Read tickets, comments and labels',
        'tickets:write' => 'Create and update tickets, and post comments',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Keys belonging to a tenant. Null is the single-tenant install, whose
     * rows carry a null tenant_id.
     */
    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }
}
