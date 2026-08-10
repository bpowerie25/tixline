<?php

namespace App\Services;

use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WorkflowEngine
{
    public function run(Ticket $ticket, string $event): void
    {
        $workflows = Workflow::where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($workflows as $workflow) {
            // Check if any of the workflow's events match
            if (! $this->eventMatches($workflow, $event)) {
                continue;
            }

            if ($this->evaluateConditions($ticket, $workflow->conditions)) {
                $this->executeActions($ticket, $workflow->actions);
            }
        }
    }

    protected function eventMatches(Workflow $workflow, string $event): bool
    {
        // Support both legacy single trigger_event and new events array
        $events = $workflow->events ?? [];

        if (! empty($events)) {
            foreach ($events as $e) {
                $entity = $e['entity'] ?? 'ticket';
                $action = $e['action'] ?? '';
                if ($entity === 'ticket' && $action === $event) {
                    return true;
                }
                // Also match the combined format
                if ($action === $event || "{$entity}_{$action}" === $event) {
                    return true;
                }
            }

            return false;
        }

        // Legacy: single trigger_event field
        return $workflow->trigger_event === $event;
    }

    /**
     * Evaluate conditions with support for nested AND/OR groups.
     *
     * Structure:
     * {
     *   "match": "all",        // "all" = AND, "any" = OR
     *   "rules": [
     *     {"field": "subject", "operator": "contains", "value": "billing"},
     *     {
     *       "match": "any",    // nested group
     *       "rules": [
     *         {"field": "priority", "operator": "equals", "value": "high"},
     *         {"field": "priority", "operator": "equals", "value": "urgent"}
     *       ]
     *     }
     *   ]
     * }
     */
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
            // If the rule has a 'match' key, it's a nested group — recurse
            if (isset($rule['match'])) {
                $result = $this->evaluateConditions($ticket, $rule);
            } else {
                $result = $this->evaluateRule($ticket, $rule);
            }

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
            'requester_name' => $ticket->requester_name,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'source' => $ticket->source,
            'team_id' => (string) $ticket->team_id,
            'assigned_to' => (string) $ticket->assigned_to,
            default => $ticket->custom_fields[$field] ?? '',
        };

        return match ($operator) {
            'equals' => strtolower((string) $ticketValue) === strtolower((string) $value),
            'not_equals' => strtolower((string) $ticketValue) !== strtolower((string) $value),
            'contains' => str_contains(strtolower((string) $ticketValue), strtolower((string) $value)),
            'not_contains' => ! str_contains(strtolower((string) $ticketValue), strtolower((string) $value)),
            'starts_with' => str_starts_with(strtolower((string) $ticketValue), strtolower((string) $value)),
            'ends_with' => str_ends_with(strtolower((string) $ticketValue), strtolower((string) $value)),
            'is_empty' => empty($ticketValue),
            'is_not_empty' => ! empty($ticketValue),
            'in' => in_array(strtolower((string) $ticketValue), array_map('strtolower', (array) $value)),
            'not_in' => ! in_array(strtolower((string) $ticketValue), array_map('strtolower', (array) $value)),
            'matches_regex' => $this->safeRegexMatch($value, (string) $ticketValue),
            default => false,
        };
    }

    protected function executeActions(Ticket $ticket, array $actions): void
    {
        foreach ($actions as $action) {
            match ($action['type'] ?? '') {
                'assign_to_agent' => $ticket->update(['assigned_to' => $action['value']]),
                'assign_to_team' => $ticket->update(['team_id' => $action['value']]),
                'set_priority' => $ticket->update(['priority' => $action['value']]),
                'set_status' => $ticket->update(['status' => $action['value']]),
                'add_label' => $ticket->labels()->syncWithoutDetaching([$action['value']]),
                'remove_label' => $ticket->labels()->detach([$action['value']]),
                'assign_to_matching_team' => $this->assignToMatchingTeam($ticket, $action),
                'mail_matching_team' => $this->mailMatchingTeam($ticket, $action),
                'round_robin' => $this->roundRobinAssign($ticket, $action),
                'mail_agent' => $this->mailAgent($ticket, $action),
                'mail_team' => $this->mailTeam($ticket, $action),
                'mail_requester' => $this->mailRequester($ticket, $action),
                'add_note' => $this->addNote($ticket, $action),
                'send_webhook' => $this->sendWebhook($ticket, $action),
                default => null,
            };
        }
    }

    protected function assignToMatchingTeam(Ticket $ticket, array $action): void
    {
        $fieldName = $action['value'] ?? '';

        if (empty($fieldName)) {
            return;
        }

        $fieldValue = $ticket->custom_fields[$fieldName] ?? '';

        if (empty($fieldValue)) {
            return;
        }

        // Find a team whose name matches the field value (case-insensitive)
        $team = Team::whereRaw('LOWER(name) = ?', [strtolower($fieldValue)])->first();

        if ($team) {
            $ticket->update(['team_id' => $team->id]);
        } else {
            Log::info('Workflow: no matching team found for field value', [
                'field' => $fieldName,
                'value' => $fieldValue,
                'ticket' => $ticket->reference,
            ]);
        }
    }

    protected function mailMatchingTeam(Ticket $ticket, array $action): void
    {
        $fieldName = $action['value'] ?? '';

        if (empty($fieldName)) {
            return;
        }

        $fieldValue = $ticket->custom_fields[$fieldName] ?? '';

        if (empty($fieldValue)) {
            return;
        }

        $team = Team::with('members')->whereRaw('LOWER(name) = ?', [strtolower($fieldValue)])->first();

        if (! $team || $team->members->isEmpty()) {
            return;
        }

        $template = $action['template'] ?? "Ticket [{$ticket->reference}] {$ticket->subject} has been assigned to your team.";
        $recipients = $team->members->pluck('email')->toArray();

        Mail::raw($template, function ($message) use ($recipients, $ticket) {
            $message->to($recipients)
                ->subject("[{$ticket->reference}] {$ticket->subject}");
        });
    }

    protected function roundRobinAssign(Ticket $ticket, array $action): void
    {
        $teamId = $action['value'] ?? $ticket->team_id;

        if (! $teamId) {
            return;
        }

        $team = Team::find($teamId);
        $agents = $team ? $team->members()->pluck('users.id') : collect();

        if ($agents->isEmpty()) {
            return;
        }

        $lastAssigned = Ticket::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->whereNotNull('assigned_to')
            ->latest()
            ->value('assigned_to');

        $currentIndex = $lastAssigned ? $agents->search($lastAssigned) : -1;
        $nextIndex = ($currentIndex + 1) % $agents->count();

        $ticket->update(['assigned_to' => $agents[$nextIndex]]);
    }

    protected function mailAgent(Ticket $ticket, array $action): void
    {
        $agentId = $action['value'] ?? $ticket->assigned_to;
        $agent = $agentId ? User::find($agentId) : null;

        if (! $agent) {
            return;
        }

        $template = $action['template'] ?? "You have been assigned ticket [{$ticket->reference}] {$ticket->subject}";

        Mail::raw($template, function ($message) use ($agent, $ticket) {
            $message->to($agent->email)
                ->subject("[{$ticket->reference}] {$ticket->subject}");
        });
    }

    protected function mailTeam(Ticket $ticket, array $action): void
    {
        $teamId = $action['value'] ?? $ticket->team_id;
        $team = $teamId ? Team::with('members')->find($teamId) : null;

        if (! $team || $team->members->isEmpty()) {
            return;
        }

        $template = $action['template'] ?? "Ticket [{$ticket->reference}] {$ticket->subject} has been assigned to your team.";

        $recipients = $team->members->pluck('email')->toArray();

        Mail::raw($template, function ($message) use ($recipients, $ticket) {
            $message->to($recipients)
                ->subject("[{$ticket->reference}] {$ticket->subject}");
        });
    }

    protected function mailRequester(Ticket $ticket, array $action): void
    {
        $template = $action['template'] ?? "Your ticket [{$ticket->reference}] has been received. We'll get back to you shortly.";

        Mail::raw($template, function ($message) use ($ticket) {
            $message->to($ticket->requester_email)
                ->subject("[{$ticket->reference}] {$ticket->subject}");
        });
    }

    protected function addNote(Ticket $ticket, array $action): void
    {
        $ticket->comments()->create([
            'body' => $action['value'] ?? 'Automated workflow note',
            'type' => 'note',
            'is_internal' => true,
        ]);
    }

    protected function sendWebhook(Ticket $ticket, array $action): void
    {
        $url = $action['value'] ?? '';

        if (empty($url)) {
            return;
        }

        try {
            Http::timeout(10)->post($url, [
                'event' => 'workflow_triggered',
                'ticket' => [
                    'id' => $ticket->id,
                    'reference' => $ticket->reference,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'requester_email' => $ticket->requester_email,
                    'assigned_to' => $ticket->assigned_to,
                    'team_id' => $ticket->team_id,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Workflow webhook failed', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Safely execute a regex match, escaping the delimiter and suppressing errors.
     */
    protected function safeRegexMatch(string $pattern, string $subject): bool
    {
        if ($pattern === '') {
            return false;
        }

        // Escape the delimiter in the user-supplied pattern
        $escaped = str_replace('~', '\~', $pattern);
        $result = @preg_match('~'.$escaped.'~i', $subject);

        if ($result === false) {
            Log::warning('Workflow regex failed', [
                'pattern' => $pattern,
                'error' => preg_last_error_msg(),
            ]);

            return false;
        }

        return (bool) $result;
    }

    /**
     * Validate that a regex pattern is syntactically valid.
     * Returns true if valid, false otherwise.
     */
    public static function isValidRegex(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }

        $escaped = str_replace('~', '\~', $pattern);

        return @preg_match('~'.$escaped.'~i', '') !== false;
    }

    /**
     * Public wrappers for use by AutomationEngine.
     */
    public function evaluateConditionsPublic(Ticket $ticket, array $conditions): bool
    {
        return $this->evaluateConditions($ticket, $conditions);
    }

    public function executeActionsPublic(Ticket $ticket, array $actions): void
    {
        $this->executeActions($ticket, $actions);
    }
}
