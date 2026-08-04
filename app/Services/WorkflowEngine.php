<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;

class WorkflowEngine
{
    public function run(Ticket $ticket, string $event): void
    {
        $workflows = Workflow::where('is_active', true)
            ->where('trigger_event', $event)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($workflows as $workflow) {
            if ($this->evaluateConditions($ticket, $workflow->conditions)) {
                $this->executeActions($ticket, $workflow->actions);
            }
        }
    }

    protected function evaluateConditions(Ticket $ticket, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $match = $conditions['match'] ?? 'all';
        $rules = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($ticket, $rule);

            if ($match === 'any' && $result) {
                return true;
            }
            if ($match === 'all' && ! $result) {
                return false;
            }
        }

        return $match === 'all';
    }

    protected function evaluateRule(Ticket $ticket, array $rule): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? '';

        $ticketValue = match ($field) {
            'subject' => $ticket->subject,
            'body' => $ticket->body,
            'requester_email' => $ticket->requester_email,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'source' => $ticket->source,
            default => $ticket->custom_fields[$field] ?? '',
        };

        return match ($operator) {
            'equals' => strtolower($ticketValue) === strtolower($value),
            'not_equals' => strtolower($ticketValue) !== strtolower($value),
            'contains' => str_contains(strtolower($ticketValue), strtolower($value)),
            'not_contains' => ! str_contains(strtolower($ticketValue), strtolower($value)),
            'starts_with' => str_starts_with(strtolower($ticketValue), strtolower($value)),
            'ends_with' => str_ends_with(strtolower($ticketValue), strtolower($value)),
            'is_empty' => empty($ticketValue),
            'is_not_empty' => ! empty($ticketValue),
            default => false,
        };
    }

    protected function executeActions(Ticket $ticket, array $actions): void
    {
        foreach ($actions as $action) {
            match ($action['type'] ?? '') {
                'assign_to_agent' => $this->assignToAgent($ticket, $action),
                'assign_to_team' => $this->assignToTeam($ticket, $action),
                'set_priority' => $ticket->update(['priority' => $action['value']]),
                'set_status' => $ticket->update(['status' => $action['value']]),
                'add_label' => $ticket->labels()->syncWithoutDetaching([$action['value']]),
                'round_robin' => $this->roundRobinAssign($ticket, $action),
                default => null,
            };
        }
    }

    protected function assignToAgent(Ticket $ticket, array $action): void
    {
        $ticket->update(['assigned_to' => $action['value']]);
    }

    protected function assignToTeam(Ticket $ticket, array $action): void
    {
        $ticket->update(['team_id' => $action['value']]);
    }

    protected function roundRobinAssign(Ticket $ticket, array $action): void
    {
        $teamId = $action['value'] ?? $ticket->team_id;

        if (! $teamId) {
            return;
        }

        $agents = User::where('team_id', $teamId)->pluck('id');

        if ($agents->isEmpty()) {
            return;
        }

        $lastAssigned = Ticket::where('team_id', $teamId)
            ->whereNotNull('assigned_to')
            ->latest()
            ->value('assigned_to');

        $currentIndex = $lastAssigned ? $agents->search($lastAssigned) : -1;
        $nextIndex = ($currentIndex + 1) % $agents->count();

        $ticket->update(['assigned_to' => $agents[$nextIndex]]);
    }
}
