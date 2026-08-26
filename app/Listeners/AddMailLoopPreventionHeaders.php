<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

class AddMailLoopPreventionHeaders
{
    /**
     * Stamp loop-prevention headers on every outbound message.
     *
     * A helpdesk both sends mail to and receives mail from the same addresses,
     * so an out-of-office autoresponder on the far end can bounce our own mail
     * straight back into the inbox and open a new ticket. These headers ask the
     * recipient's server not to generate an automatic response.
     *
     * X-Auto-Response-Suppress (Exchange/Outlook) is applied to all mail.
     * Auto-Submitted (RFC 3834) is applied only to machine-generated mail --
     * a mailable that is genuinely written by a human sets "no" itself, and we
     * never override a header the sender has already chosen.
     */
    public function handle(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();

        if (! $headers->has('X-Auto-Response-Suppress')) {
            $headers->addTextHeader('X-Auto-Response-Suppress', 'All');
        }

        if (! $headers->has('Auto-Submitted')) {
            $headers->addTextHeader('Auto-Submitted', 'auto-generated');
        }
    }
}
