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

        // Response SLA breached — no first response and past deadline (fire once)
        $responseBreached = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNull('first_responded_at')
            ->whereNotNull('sla_response_due_at')
            ->where('sla_response_due_at', '<=', $now)
            ->where('sla_response_breach_fired', false)
            ->get();

        foreach ($responseBreached as $ticket) {
            $engine->run($ticket, 'sla_response_breached');
            $ticket->updateQuietly(['sla_response_breach_fired' => true]);
            $this->line("  SLA response breached: {$ticket->reference}");
        }

        // Resolution SLA breached — past deadline and not resolved (fire once)
        $resolutionBreached = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<=', $now)
            ->where('sla_resolution_breach_fired', false)
            ->get();

        foreach ($resolutionBreached as $ticket) {
            $engine->run($ticket, 'sla_resolution_breached');
            $ticket->updateQuietly(['sla_resolution_breach_fired' => true]);
            $this->line("  SLA resolution breached: {$ticket->reference}");
        }

        // SLA warning — 75% of the resolution budget consumed (fire once).
        // sla_warning_at is stamped at creation off the same business-hours
        // clock as the deadlines, so this is a plain comparison rather than a
        // per-ticket recalculation.
        $atRisk = Ticket::whereIn('status', ['open', 'pending'])
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '>', $now)
            ->whereNotNull('sla_warning_at')
            ->where('sla_warning_at', '<=', $now)
            ->where('sla_warning_fired', false)
            ->get();

        foreach ($atRisk as $ticket) {
            $engine->run($ticket, 'sla_warning');
            $ticket->updateQuietly(['sla_warning_fired' => true]);
            $this->line("  SLA at risk: {$ticket->reference}");
        }

        $total = $responseBreached->count() + $resolutionBreached->count() + $atRisk->count();
        $this->info("{$total} SLA event(s) fired.");

        return self::SUCCESS;
    }
}
