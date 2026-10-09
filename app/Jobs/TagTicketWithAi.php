<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\AiTagging\AiTagger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TagTicketWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public Ticket $ticket,
    ) {}

    public function handle(AiTagger $tagger): void
    {
        $tagger->tagTicket($this->ticket);
    }
}
