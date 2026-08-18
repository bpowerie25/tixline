<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\HtmlSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'reference', 'subject', 'body', 'requester_name', 'requester_email',
        'status', 'priority', 'source', 'assigned_to', 'team_id', 'form_id', 'tenant_id',
        'custom_fields', 'first_responded_at', 'resolved_at',
        'sla_response_due_at', 'sla_resolution_due_at',
    ];

    protected $hidden = ['body'];

    protected $appends = ['sla_status', 'sanitized_body'];

    protected function casts(): array
    {
        return [
            'assigned_to' => 'integer',
            'team_id' => 'integer',
            'custom_fields' => 'array',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_response_due_at' => 'datetime',
            'sla_resolution_due_at' => 'datetime',
        ];
    }

    public static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            $updates = [];

            if (empty($ticket->reference)) {
                $updates['reference'] = 'TKT-'.str_pad($ticket->id, 6, '0', STR_PAD_LEFT);
            }

            $sla = SlaPolicy::forPriority($ticket->priority);
            if ($sla) {
                $updates['sla_response_due_at'] = $ticket->created_at->addHours($sla->first_response_hours);
                $updates['sla_resolution_due_at'] = $ticket->created_at->addHours($sla->resolution_hours);
            }

            if (! empty($updates)) {
                $ticket->updateQuietly($updates);
            }
        });
    }

    public function getSanitizedBodyAttribute(): string
    {
        return HtmlSanitizer::sanitize($this->body);
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
        if (! $this->first_responded_at && $this->sla_response_due_at && $now->gt($this->sla_response_due_at)) {
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

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
