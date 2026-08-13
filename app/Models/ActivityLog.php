<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'description', 'subject_type', 'subject_id',
        'ip_address', 'properties', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }
    }

    public function scopeForAction($query, ?string $action)
    {
        if ($action) {
            $query->where('action', $action);
        }
    }

    public function scopeForUser($query, ?int $userId)
    {
        if ($userId) {
            $query->where('user_id', $userId);
        }
    }
}
