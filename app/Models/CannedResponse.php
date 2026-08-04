<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    protected $fillable = ['name', 'shortcode', 'body', 'user_id', 'is_shared'];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interpolate(Ticket $ticket): string
    {
        return str_replace(
            ['{{requester_name}}', '{{requester_email}}', '{{ticket_reference}}', '{{ticket_subject}}', '{{agent_name}}'],
            [$ticket->requester_name, $ticket->requester_email, $ticket->reference, $ticket->subject, $ticket->assignee?->name ?? ''],
            $this->body
        );
    }
}
