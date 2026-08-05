<?php

namespace App\Console\Commands;

use App\Models\InboundEmail;
use Illuminate\Console\Command;

class PurgeInboundPayloads extends Command
{
    protected $signature = 'support:purge-payloads
                            {--days= : Override the retention period from config}';

    protected $description = 'Null the payload column on inbound_emails older than the retention period';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('support.inbound.payload_retention_days', 30));

        if ($days < 1) {
            $this->error('Retention period must be at least 1 day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = InboundEmail::where('created_at', '<', $cutoff)
            ->whereNotNull('payload')
            ->update(['payload' => null]);

        $this->info("Purged payload from {$count} inbound email(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
