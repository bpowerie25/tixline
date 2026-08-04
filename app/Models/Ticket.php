<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'reference', 'subject', 'body', 'requester_name', 'requester_email',
        'status', 'priority', 'source', 'assigned_to', 'team_id', 'form_id',
        'custom_fields', 'first_responded_at', 'resolved_at',
        'sla_response_due_at', 'sla_resolution_due_at',
    ];

    protected $appends = ['sla_status'];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_response_due_at' => 'datetime',
            'sla_resolution_due_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->reference)) {
                $ticket->reference = 'TKT-' . str_pad(static::max('id') + 1, 6, '0', STR_PAD_LEFT);
            }
        });

        static::created(function (Ticket $ticket) {
            $sla = SlaPolicy::forPriority($ticket->priority);
            if ($sla) {
                $ticket->updateQuietly([
                    'sla_response_due_at' => $ticket->created_at->addHours($sla->first_response_hours),
                    'sla_resolution_due_at' => $ticket->created_at->addHours($sla->resolution_hours),
                ]);
            }
        });
    }

    public function getSlaStatusAttribute(): ?string
    {
        if (in_array($this->status, ['resolved', 'closed'])) {
            return 'met';
        }

        $now = now();

        // Check resolution SLA
        if ($this->sla_resolution_due_at && $now->gt($this->sla_resolution_due_at)) {
            return 'breached';
        }

        // Check response SLA
        if (!$this->first_responded_at && $this->sla_response_due_at && $now->gt($this->sla_response_due_at)) {
            return 'breached';
        }

        // Warning at 75% of time elapsed
        if ($this->sla_resolution_due_at) {
            $total = $this->created_at->diffInMinutes($this->sla_resolution_due_at);
            $elapsed = $this->created_at->diffInMinutes($now);
            if ($total > 0 && ($elapsed / $total) >= 0.75) {
                return 'warning';
            }
        }

        if ($this->sla_response_due_at || $this->sla_resolution_due_at) {
            return 'on_track';
        }

        return null;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }
}
