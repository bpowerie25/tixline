<?php

namespace App\Mail;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Comment $comment,
    ) {}

    public function envelope(): Envelope
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $fromEmail = $tenant?->support_email ?? config('mail.from.address');
        $fromName = $tenant?->name ?? config('mail.from.name');

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromEmail, $fromName),
            subject: "Re: [{$this->ticket->reference}] {$this->ticket->subject}",
        );
    }

    public function build()
    {
        return $this->html($this->buildHtml());
    }

    protected function buildHtml(): string
    {
        $agentName = $this->comment->user?->name ?? 'Support';
        $body = $this->comment->body;
        $reference = $this->ticket->reference;

        return <<<HTML
        <div style="font-family: sans-serif; max-width: 600px; margin: 0 auto;">
            <p>{$body}</p>
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
            <p style="color: #6b7280; font-size: 13px;">
                {$agentName}<br />
                Ticket: {$reference}
            </p>
        </div>
        HTML;
    }
}
