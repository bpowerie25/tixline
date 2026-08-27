<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'description', 'priority', 'first_response_hours',
        'resolution_hours', 'use_business_hours', 'is_active', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'use_business_hours' => 'boolean',
        ];
    }

    public static function forPriority(?string $priority): ?self
    {
        return static::where('priority', $priority)
            ->where('is_active', true)
            ->first();
    }
}
