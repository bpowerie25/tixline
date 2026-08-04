<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AutomationEngine
{
    public function __construct(
        protected WorkflowEngine $workflowEngine,
    ) {}

    /**
     * Run all active automations against all eligible tickets.
     * Called by the scheduler every few minutes.
     */
    public function runAll(): array
    {
        $automations = Automation::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        $results = [];

        foreach ($automations as $automation) {
            $tickets = $this->findMatchingTickets($automation);

            foreach ($tickets as $ticket) {
                $this->workflowEngine->executeActionsPublic($ticket, $automation->actions);

                // Record that this automation fired for this ticket
                if ($automation->run_once_per_ticket) {
                    $automation->firedTickets()->attach($ticket->id, ['fired_at' => now()]);
                }

                $results[] = [
                    'automation' => $automation->name,
                    'ticket' => $ticket->reference,
                ];
            }
        }

        return $results;
    }

    /**
     * Run a single automation and return matching tickets.
     */
    public function run(Automation $automation): array
    {
        $tickets = $this->findMatchingTickets($automation);
        $results = [];

        foreach ($tickets as $ticket) {
            $this->workflowEngine->executeActionsPublic($ticket, $automation->actions);

            if ($automation->run_once_per_ticket) {
                $automation->firedTickets()->attach($ticket->id, ['fired_at' => now()]);
            }

            $results[] = $ticket->reference;
        }

        return $results;
    }

    protected function findMatchingTickets(Automation $automation): Collection
    {
        $query = Ticket::whereIn('status', ['open', 'pending']);

        // Exclude tickets this automation already fired on
        if ($automation->run_once_per_ticket) {
            $firedIds = $automation->firedTickets()->pluck('tickets.id');
            if ($firedIds->isNotEmpty()) {
                $query->whereNotIn('id', $firedIds);
            }
        }

        $tickets = $query->get();

        // Filter by time conditions and ticket conditions
        return $tickets->filter(function (Ticket $ticket) use ($automation) {
            return $this->evaluateTimeConditions($ticket, $automation->time_conditions)
                && $this->evaluateTicketConditions($ticket, $automation->ticket_conditions);
        });
    }

    /**
     * Time conditions check how long ago something happened.
     *
     * Format:
     * [
     *   {"field": "hours_since_created", "operator": "greater_than", "value": 4},
     *   {"field": "hours_since_last_reply", "operator": "greater_than", "value": 24},
     * ]
     */
    protected function evaluateTimeConditions(Ticket $ticket, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! $this->evaluateTimeCondition($ticket, $condition)) {
                return false;
            }
        }

        return true;
    }

    protected function evaluateTimeCondition(Ticket $ticket, array $condition): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'greater_than';
        $value = (float) ($condition['value'] ?? 0);

        $hours = $this->getTimeValue($ticket, $field);

        if ($hours === null) {
            return false;
        }

        return match ($operator) {
            'greater_than' => $hours > $value,
            'less_than' => $hours < $value,
            'equals' => abs($hours - $value) < 0.5, // within 30 min tolerance
            'between' => isset($condition['value_max']) && $hours >= $value && $hours <= (float) $condition['value_max'],
            default => false,
        };
    }

    protected function getTimeValue(Ticket $ticket, string $field): ?float
    {
        $now = now();

        return match ($field) {
            'hours_since_created' => $ticket->created_at->diffInMinutes($now) / 60,
            'hours_since_updated' => $ticket->updated_at->diffInMinutes($now) / 60,
            'hours_since_assigned' => $ticket->assigned_to
                ? $ticket->updated_at->diffInMinutes($now) / 60
                : null,
            'hours_since_first_response' => $ticket->first_responded_at
                ? $ticket->first_responded_at->diffInMinutes($now) / 60
                : null,
            'hours_without_response' => ! $ticket->first_responded_at
                ? $ticket->created_at->diffInMinutes($now) / 60
                : null,
            'hours_since_last_agent_reply' => $this->hoursSinceLastAgentReply($ticket, $now),
            'hours_since_last_customer_reply' => $this->hoursSinceLastCustomerReply($ticket, $now),
            'hours_until_sla_response' => $ticket->sla_response_due_at && ! $ticket->first_responded_at
                ? $now->diffInMinutes($ticket->sla_response_due_at, false) / 60
                : null,
            'hours_until_sla_resolution' => $ticket->sla_resolution_due_at
                ? $now->diffInMinutes($ticket->sla_resolution_due_at, false) / 60
                : null,
            default => null,
        };
    }

    protected function hoursSinceLastAgentReply(Ticket $ticket, Carbon $now): ?float
    {
        $lastReply = $ticket->comments()
            ->whereNotNull('user_id')
            ->where('type', 'reply')
            ->latest()
            ->first();

        return $lastReply ? $lastReply->created_at->diffInMinutes($now) / 60 : null;
    }

    protected function hoursSinceLastCustomerReply(Ticket $ticket, Carbon $now): ?float
    {
        $lastReply = $ticket->comments()
            ->whereNull('user_id')
            ->where('type', 'reply')
            ->latest()
            ->first();

        return $lastReply ? $lastReply->created_at->diffInMinutes($now) / 60 : null;
    }

    /**
     * Ticket conditions use the same nested AND/OR system as workflows.
     */
    protected function evaluateTicketConditions(Ticket $ticket, ?array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Reuse the workflow engine's condition evaluator
        return $this->workflowEngine->evaluateConditionsPublic($ticket, $conditions);
    }
}
