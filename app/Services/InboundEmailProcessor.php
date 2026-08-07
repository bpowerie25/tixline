<?php

namespace App\Services;

use App\DTOs\InboundMessage;
use App\Mail\CustomerAccountCreated;
use App\Models\Customer;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        $existingTicket = $this->findExistingTicket($message->fromEmail, $subject, $message);

        if ($existingTicket) {
            $comment = $existingTicket->comments()->create([
                'body' => $message->body,
                'type' => 'reply',
                'is_internal' => false,
            ]);

            $this->processAttachments($message, $comment);

            if (in_array($existingTicket->status, ['resolved', 'closed'])) {
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
            'tenant_id' => app()->bound('tenant') ? app('tenant')->id : Tenant::first()?->id,
        ]);

        $this->processAttachments($message, $ticket);

        $this->workflowEngine->run($ticket->fresh(), 'ticket_created');

        // Default to General Support if no workflow assigned a team
        $ticket->refresh();
        if (! $ticket->team_id) {
            $defaultTeam = Team::where('name', 'General Support')->first();
            if ($defaultTeam) {
                $ticket->updateQuietly(['team_id' => $defaultTeam->id]);
            }
        }

        // Auto-create customer account if one doesn't exist
        if (! Customer::where('email', $message->fromEmail)->exists()) {
            $temporaryPassword = Str::random(12);
            $customer = Customer::create([
                'name' => $fromName,
                'email' => $message->fromEmail,
                'password' => $temporaryPassword,
            ]);

            Mail::to($customer->email)->send(
                new CustomerAccountCreated($customer, $temporaryPassword, $ticket->fresh()->reference)
            );
        }

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

    protected function findExistingTicket(string $fromEmail, string $subject, InboundMessage $message): ?Ticket
    {
        if (preg_match('/\[TKT-(\d+)\]/', $subject, $matches)) {
            $ticket = Ticket::where('reference', 'TKT-'.$matches[1])
                ->whereIn('status', ['open', 'pending', 'resolved', 'closed'])
                ->first();

            if ($ticket && $this->senderEntitledToTicket($fromEmail, $ticket, $message)) {
                return $ticket;
            }

            if ($ticket) {
                Log::warning('Inbound email reference match rejected: sender not entitled', [
                    'reference' => $ticket->reference,
                    'sender' => $fromEmail,
                    'requester' => $ticket->requester_email,
                    'auth_results' => $message->authResults,
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
     * Check whether the sender is entitled to post to a ticket.
     *
     * When INBOUND_REQUIRE_AUTH_RESULTS is true (default), entitlement
     * via the agent-address branch requires passing email authentication
     * (DKIM/SPF/DMARC). The original requester is allowed without auth
     * only if the reference was already in the subject (they know it).
     * The agent branch is NEVER allowed without authentication.
     */
    protected function senderEntitledToTicket(string $fromEmail, Ticket $ticket, InboundMessage $message): bool
    {
        $email = strtolower($fromEmail);
        $requireAuth = config('support.inbound.require_auth_results', true);
        $authPassed = $message->authPassed();

        // Original requester — allowed even without auth results (they
        // already know the reference because we sent it to them)
        if (strtolower($ticket->requester_email) === $email) {
            return true;
        }

        // From here on, all branches require authentication when the
        // policy is enabled.
        if ($requireAuth && ! $authPassed) {
            return false;
        }

        // Known participant — has an existing comment on this ticket
        $isParticipant = $ticket->comments()
            ->whereHas('user', fn ($q) => $q->where('email', $email))
            ->exists();

        if ($isParticipant) {
            return true;
        }

        // Registered agent or admin — strictest: always requires auth
        $isAgent = \App\Models\User::where('email', $email)->exists();

        if ($isAgent) {
            // Even when require_auth_results is false, agent address
            // spoofing is too dangerous to allow without auth.
            if (! $authPassed) {
                Log::warning('Agent address claimed without passing email auth', [
                    'claimed' => $email,
                    'auth_results' => $message->authResults,
                ]);

                return false;
            }

            return true;
        }

        return false;
    }
}
