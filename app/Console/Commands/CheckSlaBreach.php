<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\WorkflowEngine;
use Illuminate\Console\Command;

class CheckSlaBreach extends Command
{
    protected $signature = 'support:check-sla';

    protected $description = 'Check for SLA breaches and trigger workflow events';

    public function handle(WorkflowEngine $engine): int
    {
        $now = now();

        // Response SLA breached — no first response and past deadline
        $responseBreached = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNull('first_responded_at')
            ->whereNotNull('sla_response_due_at')
            ->where('sla_response_due_at', '<=', $now)
            ->get();

        foreach ($responseBreached as $ticket) {
            $engine->run($ticket, 'sla_response_breached');
            $this->line("  SLA response breached: {$ticket->reference}");
        }

        // Resolution SLA breached — past deadline and not resolved
        $resolutionBreached = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<=', $now)
            ->get();

        foreach ($resolutionBreached as $ticket) {
            $engine->run($ticket, 'sla_resolution_breached');
            $this->line("  SLA resolution breached: {$ticket->reference}");
        }

        // SLA warning — 75% of time elapsed
        $atRisk = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '>', $now)
            ->get()
            ->filter(function ($ticket) use ($now) {
                $total = $ticket->created_at->diffInMinutes($ticket->sla_resolution_due_at);
                $elapsed = $ticket->created_at->diffInMinutes($now);

                return $total > 0 && ($elapsed / $total) >= 0.75;
            });

        foreach ($atRisk as $ticket) {
            $engine->run($ticket, 'sla_warning');
            $this->line("  SLA at risk: {$ticket->reference}");
        }

        $total = $responseBreached->count() + $resolutionBreached->count() + $atRisk->count();
        $this->info("{$total} SLA event(s) fired.");

        return self::SUCCESS;
    }
}
