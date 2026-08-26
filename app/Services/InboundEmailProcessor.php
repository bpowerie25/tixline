<?php

namespace App\Services;

use App\DTOs\InboundMessage;
use App\Helpers\EmailLayout;
use App\Mail\CustomerAccountCreated;
use App\Models\Customer;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\TenantContext;
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
        $tenant = $this->requireTenant();

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

            TicketMessage::remember($existingTicket, $message->messageId, 'inbound');

            if (in_array($existingTicket->status, ['pending', 'resolved', 'closed'])) {
                $existingTicket->update(['status' => 'open']);
            }

            $this->notifyAgentOrTeam($existingTicket);

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
            'tenant_id' => $tenant?->id ?? Tenant::first()?->id,
        ]);

        $this->processAttachments($message, $ticket);

        TicketMessage::remember($ticket, $message->messageId, 'inbound');

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

    /**
     * Resolve the tenant this message belongs to, refusing to guess.
     *
     * Every query in this class -- thread matching, customer lookup, the
     * default team -- is tenant-scoped through a global scope that only
     * applies when a tenant is bound. Processing mail with nothing bound used
     * to fall back to Tenant::first(), which searched across all tenants and
     * filed the resulting ticket into whichever tenant happened to be oldest.
     * Callers must establish the context with TenantContext::run() instead.
     */
    protected function requireTenant(): ?Tenant
    {
        $tenant = TenantContext::current();

        if (! $tenant && config('support.multi_tenant')) {
            throw new \RuntimeException(
                'Inbound email cannot be processed without a tenant context. The caller must '
                .'establish it with TenantContext::run(); otherwise the message would be matched '
                .'against, and filed into, an arbitrary tenant.'
            );
        }

        return $tenant;
    }

    protected function notifyAgentOrTeam(Ticket $ticket): void
    {
        $recipients = [];

        if ($ticket->assigned_to) {
            $agent = $ticket->assignee;
            if ($agent) {
                $recipients[] = $agent->email;
            }
        } elseif ($ticket->team_id) {
            $team = $ticket->team()->with('members')->first();
            if ($team) {
                $recipients = $team->members->pluck('email')->toArray();
            }
        }

        if (empty($recipients)) {
            return;
        }

        $html = EmailLayout::wrap(
            "<p>Ticket [{$ticket->reference}] {$ticket->subject} has a new reply from {$ticket->requester_name}.</p>"
        );

        Mail::html($html, function ($message) use ($recipients, $ticket) {
            $message->to($recipients)
                ->subject("[{$ticket->reference}] New reply: {$ticket->subject}");
        });
    }

    protected function processAttachments(InboundMessage $message, $attachable): void
    {
        foreach ($message->attachments as $attachmentData) {
            $this->attachmentService->storeFromWebhook($attachmentData, $attachable);
        }
    }

    protected function findExistingTicket(string $fromEmail, string $subject, InboundMessage $message): ?Ticket
    {
        // Header-based threading first: In-Reply-To and References are set by
        // the replying mail client and survive subject edits and localised
        // "Re:"/"AW:"/"R:" prefixes, which the subject match below does not.
        if ($ticket = $this->findByReferences($message)) {
            return $ticket;
        }

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
     * Find the ticket a reply belongs to from its threading headers.
     *
     * In-Reply-To names the immediate parent; References carries the whole
     * chain, so it is walked newest-first. Both are matched against the
     * Message-IDs we have recorded for this tenant, which means a reply can
     * only ever resolve to a ticket belonging to the tenant it was sent to.
     */
    protected function findByReferences(InboundMessage $message): ?Ticket
    {
        $candidates = [];

        foreach (['in-reply-to', 'references'] as $header) {
            $value = $message->headers[$header] ?? '';

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            preg_match_all('/<([^<>]+)>/', $value, $matches);

            $ids = $matches[1] ?? [];

            // References is oldest-first; the nearest ancestor is the best match.
            $candidates = array_merge($candidates, $header === 'references' ? array_reverse($ids) : $ids);
        }

        foreach (array_unique($candidates) as $messageId) {
            $record = TicketMessage::where('message_id', TicketMessage::normalise($messageId))->first();

            if ($record && $record->ticket) {
                return $record->ticket;
            }
        }

        return null;
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
