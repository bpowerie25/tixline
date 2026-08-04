<?php

namespace App\Jobs;

use App\DTOs\InboundMessage;
use App\Models\InboundEmail;
use App\Services\InboundEmailProcessor;
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
        $record = InboundEmail::find($this->inboundEmailId);

        if (! $record || $record->status !== 'pending') {
            return;
        }

        $message = InboundMessage::fromWebhookPayload($record->payload);

        $result = $processor->process($message);

        $record->update([
            'status' => $result['status'] === 'rejected' ? 'rejected' : 'processed',
            'result' => json_encode($result),
            'processed_at' => now(),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $record = InboundEmail::find($this->inboundEmailId);

        if ($record) {
            $record->update([
                'status' => 'failed',
                'result' => $e->getMessage(),
            ]);
        }
    }
}
