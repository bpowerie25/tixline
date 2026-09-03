<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WidgetDataService
{
    public function getData(string $widgetType, array $filters, User $user): array
    {
        $query = $user->visibleTicketsQuery();
        $this->applyFilters($query, $filters);

        $data = match ($widgetType) {
            'tickets_by_status' => $this->ticketsByStatus($query),
            'tickets_by_priority' => $this->ticketsByPriority($query),
            'tickets_by_team' => $this->ticketsByTeam($query),
            'tickets_by_agent' => $this->ticketsByAgent($query),
            'tickets_by_source' => $this->ticketsBySource($query),
            'tickets_by_label' => $this->ticketsByLabel($query),
            'ticket_volume' => $this->ticketVolume($query),
            'avg_response_time' => $this->avgResponseTime($query),
            'avg_resolution_time' => $this->avgResolutionTime($query),
            'avg_resolution_time_business' => $this->avgResolutionTimeBusiness($query),
            'sla_compliance' => $this->slaCompliance($query),
            'agent_performance' => $this->agentPerformance($query, $filters),
            'ticket_list' => $this->ticketList($query),
            default => ['labels' => [], 'values' => []],
        };

        // Merge user color overrides into the colorMap
        if (! empty($filters['color_overrides']) && isset($data['labels'])) {
            $data['colorMap'] = array_merge($data['colorMap'] ?? [], $filters['color_overrides']);
        }

        return $data;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (! empty($filters['days'])) {
            $query->where('created_at', '>=', now()->subDays((int) $filters['days']));
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['agent_id'])) {
            $query->where('assigned_to', $filters['agent_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['label_ids']) && is_array($filters['label_ids'])) {
            $query->whereHas('labels', function ($q) use ($filters) {
                $q->whereIn('labels.id', $filters['label_ids']);
            });
        }
    }

    private function ticketsByStatus(Builder $query): array
    {
        $results = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
            'colorMap' => [
                'open' => '#22c55e',
                'pending' => '#eab308',
                'resolved' => '#3b82f6',
                'closed' => '#9ca3af',
            ],
        ];
    }

    private function ticketsByPriority(Builder $query): array
    {
        $results = $query->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
            'colorMap' => [
                'low' => '#9ca3af',
                'normal' => '#3b82f6',
                'high' => '#f97316',
                'urgent' => '#ef4444',
            ],
        ];
    }

    private function ticketsByTeam(Builder $query): array
    {
        $results = $query->join('teams', 'tickets.team_id', '=', 'teams.id')
            ->selectRaw('teams.name as team_name, COUNT(*) as count')
            ->groupBy('tickets.team_id', 'teams.name')
            ->pluck('count', 'team_name');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
        ];
    }

    private function ticketsByAgent(Builder $query): array
    {
        $results = $query->join('users', 'tickets.assigned_to', '=', 'users.id')
            ->selectRaw('users.name as agent_name, COUNT(*) as count')
            ->groupBy('tickets.assigned_to', 'users.name')
            ->pluck('count', 'agent_name');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
        ];
    }

    private function ticketsBySource(Builder $query): array
    {
        $results = $query->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
        ];
    }

    private function ticketsByLabel(Builder $query): array
    {
        $results = $query->join('label_ticket', 'tickets.id', '=', 'label_ticket.ticket_id')
            ->join('labels', 'label_ticket.label_id', '=', 'labels.id')
            ->selectRaw('labels.name as label_name, COUNT(*) as count')
            ->groupBy('label_ticket.label_id', 'labels.name')
            ->pluck('count', 'label_name');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
        ];
    }

    private function ticketVolume(Builder $query): array
    {
        $results = $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'labels' => $results->keys()->all(),
            'values' => $results->values()->all(),
        ];
    }

    private function avgResponseTime(Builder $query): array
    {
        $tickets = (clone $query)->whereNotNull('first_responded_at')
            ->get(['created_at', 'first_responded_at']);

        if ($tickets->isEmpty()) {
            return ['value' => null];
        }

        $totalHours = $tickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at) / 60);

        return ['value' => round($totalHours / $tickets->count(), 1)];
    }

    private function avgResolutionTime(Builder $query): array
    {
        $tickets = (clone $query)->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        if ($tickets->isEmpty()) {
            return ['value' => null];
        }

        $totalHours = $tickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);

        return ['value' => round($totalHours / $tickets->count(), 1)];
    }

    private function avgResolutionTimeBusiness(Builder $query): array
    {
        $tickets = (clone $query)->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        if ($tickets->isEmpty()) {
            return ['value' => null];
        }

        $totalHours = $tickets->sum(fn ($t) => $this->calculateBusinessHours($t->created_at, $t->resolved_at));

        return ['value' => round($totalHours / $tickets->count(), 1)];
    }

    private function calculateBusinessHours(\Carbon\Carbon $start, \Carbon\Carbon $end): float
    {
        $hours = 0;
        $current = $start->copy();

        while ($current->lt($end)) {
            if ($current->isWeekday()) {
                $endOfDay = $current->copy()->endOfDay();
                if ($endOfDay->gt($end)) {
                    $hours += $current->diffInMinutes($end) / 60;
                } else {
                    $hours += $current->diffInMinutes($endOfDay) / 60;
                }
            }

            $current = $current->copy()->addDay()->startOfDay();
        }

        return $hours;
    }

    private function slaCompliance(Builder $query): array
    {
        $tickets = (clone $query)
            ->where(function ($q) {
                $q->whereNotNull('sla_response_due_at')
                    ->orWhereNotNull('sla_resolution_due_at');
            })
            ->get(['status', 'first_responded_at', 'sla_response_due_at', 'resolved_at', 'sla_resolution_due_at', 'created_at']);

        $met = 0;
        $breached = 0;

        foreach ($tickets as $ticket) {
            $slaStatus = $ticket->sla_status;
            if ($slaStatus === 'met' || $slaStatus === 'on_track') {
                $met++;
            } elseif ($slaStatus === 'breached') {
                $breached++;
            }
        }

        return [
            'labels' => ['Met', 'Breached'],
            'values' => [$met, $breached],
        ];
    }

    private function ticketList(Builder $query): array
    {
        $tickets = (clone $query)
            ->with(['assignee:id,name', 'team:id,name'])
            ->latest()
            ->limit(100)
            ->get(['id', 'reference', 'subject', 'requester_name', 'status', 'priority', 'assigned_to', 'team_id', 'created_at']);

        $rows = $tickets->map(fn ($t) => [
            'Reference' => $t->reference,
            'Subject' => $t->subject,
            'Requester' => $t->requester_name,
            'Team' => $t->team?->name ?? '-',
            'Assigned To' => $t->assignee?->name ?? 'Unassigned',
            'Status' => $t->status,
            'Priority' => $t->priority,
            'Submitted' => $t->created_at->format('d M Y H:i'),
        ])->all();

        return [
            'columns' => ['Reference', 'Subject', 'Requester', 'Team', 'Assigned To', 'Status', 'Priority', 'Submitted'],
            'rows' => $rows,
        ];
    }

    private function agentPerformance(Builder $query, array $filters): array
    {
        $agentIds = (clone $query)->whereNotNull('assigned_to')
            ->distinct()
            ->pluck('assigned_to');

        $rows = [];

        foreach (User::whereIn('id', $agentIds)->get(['id', 'name']) as $agent) {
            $agentQuery = (clone $query)->where('assigned_to', $agent->id);

            $totalAssigned = (clone $agentQuery)->count();

            $responseTickets = (clone $agentQuery)->whereNotNull('first_responded_at')
                ->get(['created_at', 'first_responded_at']);

            $avgResponse = null;
            if ($responseTickets->isNotEmpty()) {
                $totalHours = $responseTickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at) / 60);
                $avgResponse = round($totalHours / $responseTickets->count(), 1);
            }

            $resolvedTickets = (clone $agentQuery)->whereNotNull('resolved_at')
                ->get(['created_at', 'resolved_at']);

            $avgResolution = null;
            if ($resolvedTickets->isNotEmpty()) {
                $totalHours = $resolvedTickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60);
                $avgResolution = round($totalHours / $resolvedTickets->count(), 1);
            }

            $rows[] = [
                'agent' => $agent->name,
                'total_assigned' => $totalAssigned,
                'avg_response_hours' => $avgResponse,
                'avg_resolution_hours' => $avgResolution,
            ];
        }

        return [
            'columns' => ['Agent', 'Assigned', 'Avg Response (hrs)', 'Avg Resolution (hrs)'],
            'rows' => $rows,
        ];
    }
}
