<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $fillable = [
        'name', 'description', 'priority', 'first_response_hours',
        'resolution_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function forPriority(string $priority): ?self
    {
        return static::where('priority', $priority)
            ->where('is_active', true)
            ->first();
    }
}
