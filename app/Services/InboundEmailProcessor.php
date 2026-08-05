<?php

namespace App\Services;

use App\DTOs\InboundMessage;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;

class InboundEmailProcessor
{
    public function __construct(
        protected SpamFilter $spamFilter,
        protected WorkflowEngine $workflowEngine,
        protected AttachmentService $attachmentService,
    ) {}

    public function process(InboundMessage $message): array
    {
        $subject = $message->subject ?: '(No Subject)';
        $fromName = $message->fromName ?: $message->fromEmail;

        // Spam check
        $spamReason = $this->spamFilter->isSpam($message->fromEmail, $subject, $message->body, $message->headers);
        if ($spamReason) {
            return ['status' => 'rejected', 'reason' => $spamReason];
        }

        // Thread detection
        $existingTicket = $this->findExistingTicket($message->fromEmail, $subject);

        if ($existingTicket) {
            $comment = $existingTicket->comments()->create([
                'body' => $message->body,
                'type' => 'reply',
                'is_internal' => false,
            ]);

            $this->processAttachments($message, $comment);

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
            'body' => $message->body,
            'requester_name' => $fromName,
            'requester_email' => $message->fromEmail,
            'source' => 'email',
        ]);

        $this->processAttachments($message, $ticket);

        $this->workflowEngine->run($ticket->fresh(), 'ticket_created');

        return [
            'status' => 'created',
            'ticket_id' => $ticket->id,
            'reference' => $ticket->fresh()->reference,
        ];
    }

    protected function processAttachments(InboundMessage $message, $attachable): void
    {
        foreach ($message->attachments as $attachmentData) {
            $this->attachmentService->storeFromWebhook($attachmentData, $attachable);
        }
    }

    protected function findExistingTicket(string $fromEmail, string $subject): ?Ticket
    {
        if (preg_match('/\[TKT-(\d+)\]/', $subject, $matches)) {
            $ticket = Ticket::where('reference', 'TKT-'.$matches[1])
                ->whereIn('status', ['open', 'pending', 'resolved'])
                ->first();

            if ($ticket && $this->senderEntitledToTicket($fromEmail, $ticket)) {
                return $ticket;
            }

            if ($ticket) {
                Log::warning('Inbound email reference match rejected: sender not entitled', [
                    'reference' => $ticket->reference,
                    'sender' => $fromEmail,
                    'requester' => $ticket->requester_email,
                ]);
                // Fall through — do NOT append to this ticket
            }
        }

        $cleanSubject = preg_replace('/^(Re|Fwd|Fw):\s*/i', '', $subject);

        return Ticket::where('requester_email', $fromEmail)
            ->where('subject', $cleanSubject)
            ->whereIn('status', ['open', 'pending'])
            ->first();
    }

    /**
     * Check whether the sender is entitled to post to a ticket:
     * - Matches the original requester email, OR
     * - Has previously commented on this ticket (known participant), OR
     * - Is a registered agent/admin in the system.
     */
    protected function senderEntitledToTicket(string $fromEmail, Ticket $ticket): bool
    {
        $email = strtolower($fromEmail);

        // Original requester
        if (strtolower($ticket->requester_email) === $email) {
            return true;
        }

        // Known participant — has an existing comment on this ticket
        $isParticipant = $ticket->comments()
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($isParticipant) {
            return true;
        }

        // Registered agent or admin
        $isAgent = \App\Models\User::where('email', $email)->exists();

        if ($isAgent) {
            return true;
        }

        return false;
    }
}
