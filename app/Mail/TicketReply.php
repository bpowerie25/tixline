<?php

namespace App\Mail;

use App\Helpers\EmailLayout;
use App\Models\Comment;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReply extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Comment $comment,
        public ?Tenant $tenant = null,
    ) {}

    public function envelope(): Envelope
    {
        $fromEmail = $this->tenant?->support_email ?? config('mail.from.address');
        $fromName = $this->tenant?->name ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: "Re: [{$this->ticket->reference}] {$this->ticket->subject}",
        );
    }

    public function build()
    {
        $mode = $this->tenant?->reply_email_mode ?? 'notification';
        $ticketUrl = EmailLayout::portalTicketUrl($this->ticket, $this->tenant);

        $html = $mode === 'full'
            ? $this->buildFullHtml($ticketUrl)
            : $this->buildNotificationHtml($ticketUrl);

        return $this->html(EmailLayout::wrap($html, $ticketUrl, $this->tenant));
    }

    protected function buildNotificationHtml(string $ticketUrl): string
    {
        $reference = e($this->ticket->reference);
        $primaryColor = $this->tenant?->primary_color ?? '#be123c';

        return <<<HTML
        <p style="font-size: 15px; color: #374151;">
            A reply has been added to your ticket <a href="{$ticketUrl}" style="color: {$primaryColor}; text-decoration: underline; font-weight: 600;">{$reference}</a>.
        </p>
        <p style="font-size: 14px; color: #6b7280;">
            Log in to the portal to view the response.
        </p>
        HTML;
    }

    protected function buildFullHtml(string $ticketUrl): string
    {
        $agentName = e($this->comment->user?->name ?? 'Support');
        $body = $this->comment->sanitized_body;
        $reference = e($this->ticket->reference);
        $primaryColor = $this->tenant?->primary_color ?? '#be123c';

        $attachmentHtml = $this->buildAttachmentHtml($ticketUrl);

        return <<<HTML
        <p>{$body}</p>
        {$attachmentHtml}
        <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 24px 0;" />
        <p style="color: #6b7280; font-size: 13px;">
            {$agentName}<br />
            Ticket: <a href="{$ticketUrl}" style="color: {$primaryColor}; text-decoration: underline;">{$reference}</a>
        </p>
        HTML;
    }

    protected function buildAttachmentHtml(string $ticketUrl): string
    {
        $this->comment->loadMissing('attachments');
        $attachments = $this->comment->attachments;

        if ($attachments->isEmpty()) {
            return '';
        }

        $primaryColor = $this->tenant?->primary_color ?? '#be123c';
        $items = '';

        foreach ($attachments as $attachment) {
            $name = e($attachment->original_filename);
            $size = $attachment->humanSize();
            $items .= "<li style=\"margin: 4px 0;\"><a href=\"{$ticketUrl}\" style=\"color: {$primaryColor}; text-decoration: underline;\">{$name}</a> <span style=\"color: #6b7280; font-size: 12px;\">({$size})</span></li>";
        }

        return <<<HTML
        <div style="margin-top: 16px;">
            <p style="font-weight: 600; font-size: 14px; color: #374151; margin: 0 0 8px;">Attachments:</p>
            <ul style="list-style: none; padding: 0; margin: 0;">{$items}</ul>
        </div>
        HTML;
    }
}
