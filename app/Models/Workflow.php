<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'description', 'trigger_event', 'events', 'conditions', 'actions',
        'is_active', 'priority', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
