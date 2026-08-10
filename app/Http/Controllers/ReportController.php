<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $days = $request->input('days', 30);
        $since = now()->subDays($days);

        // Volume by day
        $volumeByDay = Ticket::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // Status breakdown
        $statusBreakdown = Ticket::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Priority breakdown
        $priorityBreakdown = Ticket::where('created_at', '>=', $since)
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Agent performance
        $agentStats = User::whereIn('role', ['agent', 'admin'])
            ->withCount([
                'assignedTickets as total_assigned',
                'assignedTickets as resolved_count' => function ($q) use ($since) {
                    $q->where('status', 'resolved')->where('resolved_at', '>=', $since);
                },
                'assignedTickets as open_count' => function ($q) {
                    $q->where('status', 'open');
                },
            ])
            ->get(['id', 'name', 'email'])
            ->map(function ($agent) use ($since) {
                $tickets = Ticket::where('assigned_to', $agent->id)
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('first_responded_at')
                    ->get(['created_at', 'first_responded_at']);

                if ($tickets->isNotEmpty()) {
                    $totalHours = $tickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->first_responded_at) / 60);
                    $agent->avg_response_hours = round($totalHours / $tickets->count(), 1);
                } else {
                    $agent->avg_response_hours = null;
                }

                return $agent;
            });

        // Source breakdown
        $sourceBreakdown = Ticket::where('created_at', '>=', $since)
            ->selectRaw('source, COUNT(*) as count')
            ->groupBy('source')
            ->pluck('count', 'source');

        // Average resolution time
        $resolvedTickets = Ticket::where('resolved_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at']);

        $avgResolutionHours = $resolvedTickets->isNotEmpty()
            ? $resolvedTickets->sum(fn ($t) => $t->created_at->diffInMinutes($t->resolved_at) / 60) / $resolvedTickets->count()
            : null;

        // Average resolution time (business hours only - excludes weekends)
        $avgResolutionBusinessHours = null;
        if ($resolvedTickets->isNotEmpty()) {
            $totalBusinessHours = $resolvedTickets->sum(function ($t) {
                return $this->calculateBusinessHours($t->created_at, $t->resolved_at);
            });
            $avgResolutionBusinessHours = round($totalBusinessHours / $resolvedTickets->count(), 1);
        }

        return Inertia::render('Reports/Index', [
            'days' => $days,
            'volumeByDay' => $volumeByDay,
            'statusBreakdown' => $statusBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'agentStats' => $agentStats,
            'sourceBreakdown' => $sourceBreakdown,
            'avgResolutionHours' => $avgResolutionHours ? round($avgResolutionHours, 1) : null,
            'avgResolutionBusinessHours' => $avgResolutionBusinessHours,
        ]);
    }

    private function calculateBusinessHours(Carbon $start, Carbon $end): float
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
}
