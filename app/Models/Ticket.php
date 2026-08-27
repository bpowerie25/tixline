<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\BusinessHoursCalculator;
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
        'sla_response_due_at', 'sla_resolution_due_at', 'sla_warning_at',
    ];

    protected $hidden = ['body'];

    protected $appends = ['sla_status', 'sanitized_body'];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_response_due_at' => 'datetime',
            'sla_resolution_due_at' => 'datetime',
            'sla_warning_at' => 'datetime',
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
                $clock = $ticket->slaClockFor($sla);

                $updates['sla_response_due_at'] = $clock->dueAt($ticket->created_at, $sla->first_response_hours);
                $updates['sla_resolution_due_at'] = $clock->dueAt($ticket->created_at, $sla->resolution_hours);

                // Warn at 75% of the resolution budget. Stored rather than
                // derived so reading sla_status costs no queries.
                $updates['sla_warning_at'] = $clock->dueAt($ticket->created_at, $sla->resolution_hours * 0.75);
            }

            if (! empty($updates)) {
                $ticket->updateQuietly($updates);
            }
        });
    }

    /**
     * The clock this ticket's SLA deadlines run on. Policies opt out of
     * business hours individually, and a tenant with no schedule configured
     * falls back to round-the-clock.
     */
    public function slaClockFor(?SlaPolicy $policy): BusinessHoursCalculator
    {
        return BusinessHoursCalculator::for(
            $policy?->use_business_hours ? BusinessHours::forTenant($this->tenant_id) : null,
        );
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

        // Warning at 75% of the resolution budget, business hours included
        if ($this->sla_warning_at && $now->gte($this->sla_warning_at)) {
            return 'warning';
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

    /**
     * RFC 5322 Message-IDs seen on this ticket, in both directions. Used to
     * thread replies whose subject line no longer carries the reference.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * The Message-ID of the most recent message received from the customer,
     * which an outgoing reply should point at with In-Reply-To.
     */
    public function lastInboundMessageId(): ?string
    {
        return $this->messages()
            ->where('direction', 'inbound')
            ->latest('id')
            ->value('message_id');
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
