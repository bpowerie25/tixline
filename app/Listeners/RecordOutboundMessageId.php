<?php

namespace App\Listeners;

use App\Models\Scopes\TenantScope;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Mail\Events\MessageSending;

/**
 * Files the Message-ID of an outgoing ticket reply against its ticket.
 *
 * When the customer replies, their mail client puts that id in In-Reply-To and
 * References, which is how the reply threads back onto the right ticket even
 * if the subject line has been edited or re-prefixed along the way.
 *
 * This runs on the send rather than in the mailable because replies are
 * queued: the message is only handed to the transport in the worker.
 */
class RecordOutboundMessageId
{
    public function handle(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();

        if (! $headers->has('X-Tixline-Ticket')) {
            return;
        }

        $ticketId = (int) $headers->get('X-Tixline-Ticket')->getBodyAsString();
        $messageId = $headers->has('Message-ID')
            ? $headers->get('Message-ID')->getBodyAsString()
            : null;

        if (! $ticketId || ! $messageId) {
            return;
        }

        // A queue worker has no tenant bound, so the ticket is looked up
        // unscoped and its own tenant_id is what the record is written with.
        $ticket = Ticket::withoutGlobalScope(TenantScope::class)->find($ticketId);

        if ($ticket) {
            TicketMessage::remember($ticket, $messageId, 'outbound');
        }

        // Internal routing header; it should not go out on the wire.
        $headers->remove('X-Tixline-Ticket');
    }
}
