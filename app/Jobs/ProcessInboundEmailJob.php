<?php

namespace App\Jobs;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\InboundEmailProcessor;
use App\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $inboundEmailId,
    ) {}

    public function handle(InboundEmailProcessor $processor): void
    {
        // Queue workers have no request, so nothing binds a tenant for them.
        // The record carries the tenant the message was routed to at receipt;
        // without rebinding it here, processing would run unscoped and match
        // against every tenant's tickets.
        $record = InboundEmail::withoutGlobalScope(TenantScope::class)->find($this->inboundEmailId);

        if (! $record || $record->status !== 'pending') {
            return;
        }

        $tenant = $record->tenant_id
            ? Tenant::withoutGlobalScope(TenantScope::class)->find($record->tenant_id)
            : null;

        TenantContext::run($tenant, function () use ($processor, $record) {
            $message = InboundMessage::fromWebhookPayload($record->payload);

            $result = $processor->process($message);

            $record->update([
                'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
                'result' => json_encode($result),
                'processed_at' => now(),
            ]);
        });
    }

    public function failed(\Throwable $e): void
    {
        $record = InboundEmail::withoutGlobalScope(TenantScope::class)->find($this->inboundEmailId);

        if ($record) {
            $record->update([
                'status' => 'failed',
                'result' => $e->getMessage(),
            ]);
        }
    }
}
