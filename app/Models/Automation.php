<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Automation extends Model
{
    protected $fillable = [
        'name', 'description', 'time_conditions', 'ticket_conditions',
        'actions', 'is_active', 'priority', 'run_once_per_ticket',
    ];

    protected function casts(): array
    {
        return [
            'time_conditions' => 'array',
            'ticket_conditions' => 'array',
            'actions' => 'array',
            'is_active' => 'boolean',
            'run_once_per_ticket' => 'boolean',
        ];
    }

    public function firedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class)->withPivot('fired_at');
    }
}
