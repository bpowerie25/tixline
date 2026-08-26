<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'ticket_id', 'message_id', 'direction'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Normalise a raw header value to the bare id, without angle brackets.
     */
    public static function normalise(?string $messageId): ?string
    {
        $messageId = trim((string) $messageId, " \t\n\r\0\x0B<>");

        return $messageId !== '' ? $messageId : null;
    }

    /**
     * Record a Message-ID against a ticket, ignoring repeats.
     */
    public static function remember(Ticket $ticket, ?string $messageId, string $direction): void
    {
        $messageId = static::normalise($messageId);

        if (! $messageId) {
            return;
        }

        static::firstOrCreate(
            ['tenant_id' => $ticket->tenant_id, 'message_id' => $messageId],
            ['ticket_id' => $ticket->id, 'direction' => $direction],
        );
    }
}
