<?php

namespace App\Mail;

use App\Helpers\EmailLayout;
use App\Models\Comment;
use App\Models\Ticket;
use App\Services\Inbound\InboundTenantResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TicketReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Comment $comment,
    ) {}

    public function envelope(): Envelope
    {
        // Taken from the ticket, not the container: this mailable is queued,
        // and a worker has no bound tenant to fall back on.
        $tenant = $this->ticket->tenant;

        // Send from the tenant's platform address rather than their own
        // support_email. Two reasons: the platform owns this domain, so the
        // message is DMARC-aligned and does not land in spam; and replies come
        // back into the helpdesk and thread onto this ticket instead of into
        // an inbox the system never reads.
        $platformAddress = $tenant ? app(InboundTenantResolver::class)->addressFor($tenant) : null;

        $fromEmail = $platformAddress ?? $tenant?->support_email ?? config('mail.from.address');
        $fromName = $tenant?->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: "Re: [{$this->ticket->reference}] {$this->ticket->subject}",
        );
    }

    public function headers(): Headers
    {
        $parentId = $this->ticket->lastInboundMessageId();

        $text = [
            // An agent typed this reply, so it is not auto-generated (RFC 3834).
            // Suppression of the recipient's own autoresponder still applies and
            // is added globally by AddMailLoopPreventionHeaders.
            'Auto-Submitted' => 'no',
            // Lets RecordOutboundMessageId file the Message-ID we are about to
            // send against this ticket, so the customer's reply threads back.
            'X-Tixline-Ticket' => (string) $this->ticket->id,
        ];

        if ($parentId) {
            $text['In-Reply-To'] = "<{$parentId}>";
        }

        return new Headers(
            messageId: $this->outboundMessageId(),
            references: $parentId ? [$parentId] : [],
            text: $text,
        );
    }

    /**
     * A Message-ID we control, on a domain we own, so the reply that comes
     * back can be matched to this ticket without relying on the subject line.
     */
    protected function outboundMessageId(): string
    {
        $domain = config('support.inbound.domain')
            ?: parse_url((string) config('app.url'), PHP_URL_HOST)
            ?: 'localhost';

        return sprintf('tkt-%d-c%d-%s@%s', $this->ticket->id, $this->comment->id, Str::random(12), $domain);
    }

    public function build()
    {
        return $this->html(EmailLayout::wrap($this->buildHtml(), EmailLayout::portalUrl()));
    }

    protected function buildHtml(): string
    {
        $agentName = $this->comment->user?->name ?? 'Support';
        $body = $this->comment->body;
        $reference = $this->ticket->reference;

        return <<<HTML
        <p>{$body}</p>
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
        <p style="color: #6b7280; font-size: 13px;">
            {$agentName}<br />
            Ticket: {$reference}
        </p>
        HTML;
    }
}
