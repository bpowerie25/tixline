<?php

namespace App\Console\Commands;

use App\Models\AiTaggingConfig;
use App\Models\Ticket;
use App\Services\AiTagging\AiTagger;
use Illuminate\Console\Command;

class TagTickets extends Command
{
    protected $signature = 'support:tag-tickets
        {--limit=100 : Maximum number of tickets to process}
        {--reprocess : Reprocess tickets that have already been tagged}';

    protected $description = 'Run AI tagging on unprocessed tickets';

    public function handle(AiTagger $tagger): int
    {
        $config = AiTaggingConfig::active();

        if (! $config) {
            $this->warn('AI tagging is not configured or not enabled.');

            return self::SUCCESS;
        }

        $query = Ticket::whereIn('status', ['open', 'pending']);

        if (! $this->option('reprocess')) {
            $query->whereNull('ai_processed_at');
        }

        $tickets = $query->oldest()->limit((int) $this->option('limit'))->get();

        if ($tickets->isEmpty()) {
            $this->info('No tickets to process.');

            return self::SUCCESS;
        }

        $this->info("Processing {$tickets->count()} tickets...");

        $bar = $this->output->createProgressBar($tickets->count());
        $tagged = 0;
        $failed = 0;

        foreach ($tickets as $ticket) {
            if ($tagger->tagTicket($ticket)) {
                $tagged++;
            } else {
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Tagged: {$tagged}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
