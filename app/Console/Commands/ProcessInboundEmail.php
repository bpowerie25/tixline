<?php

namespace App\Console\Commands;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Inbound\InboundTenantResolver;
use App\Services\InboundEmailProcessor;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class ProcessInboundEmail extends Command
{
    protected $signature = 'support:process-email
                            {--tenant= : Slug of the tenant this mailbox belongs to. Overrides recipient-address routing.}';

    protected $description = 'Process a raw email from stdin (for Postfix pipe transport)';

    public function handle(InboundEmailProcessor $processor, InboundTenantResolver $resolver): int
    {
        $raw = file_get_contents('php://stdin');

        if (empty($raw)) {
            $this->error('No email data received on stdin.');

            return self::FAILURE;
        }

        $message = InboundMessage::fromRawEmail($raw);

        $tenant = $this->resolveTenant($message, $resolver);

        if (! $tenant && config('support.multi_tenant')) {
            // Refuse rather than guess: filing a customer's mail into the
            // wrong company's helpdesk is worse than rejecting the delivery.
            $this->error('Could not determine which tenant this message is addressed to.');

            return self::FAILURE;
        }

        return TenantContext::run($tenant, function () use ($message, $processor) {
            // Idempotency — check if we've already processed this Message-ID
            if (InboundEmail::where('message_id', $message->messageId)->exists()) {
                $this->line('Duplicate message, skipping: '.$message->messageId);

                return self::SUCCESS;
            }

            // Persist for audit trail
            $record = InboundEmail::create([
                'message_id' => $message->messageId,
                'payload' => [
                    'from_email' => $message->fromEmail,
                    'from_name' => $message->fromName,
                    'subject' => $message->subject,
                    'body' => $message->body,
                    'headers' => $message->headers,
                ],
                'auth_results' => $message->authResults,
                'status' => 'pending',
            ]);

            $result = $processor->process($message);

            $record->update([
                'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
                'result' => json_encode($result),
                'processed_at' => now(),
            ]);

            $this->line(json_encode($result));

            return self::SUCCESS;
        });
    }

    /**
     * Postfix hands over a raw message with no tenant context, so the tenant
     * comes from the address it was delivered to -- or from --tenant when the
     * transport is configured per tenant.
     */
    protected function resolveTenant(InboundMessage $message, InboundTenantResolver $resolver): ?Tenant
    {
        if ($slug = $this->option('tenant')) {
            $tenant = Tenant::withoutGlobalScope(TenantScope::class)
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                $this->error("No active tenant with slug '{$slug}'.");
            }

            return $tenant;
        }

        return $resolver->resolve([
            $message->headers['delivered-to'] ?? '',
            $message->headers['x-original-to'] ?? '',
            $message->headers['to'] ?? '',
            $message->headers['cc'] ?? '',
        ]);
    }
}
