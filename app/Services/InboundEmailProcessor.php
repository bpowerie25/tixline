<?php

namespace App\Services;

use App\Models\Ticket;

class InboundEmailProcessor
{
    public function __construct(
        protected SpamFilter $spamFilter,
        protected WorkflowEngine $workflowEngine,
    ) {}

    public function process(
        string $fromEmail,
        string $fromName,
        string $subject,
        string $body,
        array $headers = [],
    ): array {
        $subject = $subject ?: '(No Subject)';
        $fromName = $fromName ?: $fromEmail;

        // Spam check
        $spamReason = $this->spamFilter->isSpam($fromEmail, $subject, $body, $headers);
        if ($spamReason) {
            return ['status' => 'rejected', 'reason' => $spamReason];
        }

        // Thread detection — check for ticket reference in subject
        $existingTicket = $this->findExistingTicket($fromEmail, $subject);

        if ($existingTicket) {
            $existingTicket->comments()->create([
                'body' => $body,
                'type' => 'reply',
                'is_internal' => false,
            ]);

            if ($existingTicket->status === 'resolved') {
                $existingTicket->update(['status' => 'open']);
            }

            return [
                'status' => 'reply',
                'ticket_id' => $existingTicket->id,
                'reference' => $existingTicket->reference,
            ];
        }

        // New ticket
        $ticket = Ticket::create([
            'subject' => $subject,
            'body' => $body,
            'requester_name' => $fromName,
            'requester_email' => $fromEmail,
            'source' => 'email',
        ]);

        $this->workflowEngine->run($ticket->fresh(), 'ticket_created');

        return [
            'status' => 'created',
            'ticket_id' => $ticket->id,
            'reference' => $ticket->fresh()->reference,
        ];
    }

    protected function findExistingTicket(string $fromEmail, string $subject): ?Ticket
    {
        // First try to extract ticket reference from subject (e.g. "Re: [TKT-000001] ...")
        if (preg_match('/\[TKT-(\d+)\]/', $subject, $matches)) {
            $ticket = Ticket::where('reference', 'TKT-' . $matches[1])
                ->whereIn('status', ['open', 'pending', 'resolved'])
                ->first();

            if ($ticket) {
                return $ticket;
            }
        }

        // Fall back to matching by requester + subject
        $cleanSubject = preg_replace('/^(Re|Fwd|Fw):\s*/i', '', $subject);

        return Ticket::where('requester_email', $fromEmail)
            ->where('subject', $cleanSubject)
            ->whereIn('status', ['open', 'pending'])
            ->first();
    }
}
