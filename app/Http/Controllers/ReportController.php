<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $days = $request->input('days', 30);
        $since = now()->subDays($days);

        // Volume by day
        $volumeByDay = Ticket::where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        // Status breakdown
        $statusBreakdown = Ticket::selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        // Priority breakdown
        $priorityBreakdown = Ticket::where('created_at', '>=', $since)
            ->selectRaw("priority, COUNT(*) as count")
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
                $avgResponse = Ticket::where('assigned_to', $agent->id)
                    ->where('created_at', '>=', $since)
                    ->whereNotNull('first_responded_at')
                    ->selectRaw('AVG(CAST((JULIANDAY(first_responded_at) - JULIANDAY(created_at)) * 24 AS REAL)) as avg_hours')
                    ->value('avg_hours');

                $agent->avg_response_hours = $avgResponse ? round($avgResponse, 1) : null;

                return $agent;
            });

        // Source breakdown
        $sourceBreakdown = Ticket::where('created_at', '>=', $since)
            ->selectRaw("source, COUNT(*) as count")
            ->groupBy('source')
            ->pluck('count', 'source');

        // Average resolution time
        $avgResolutionHours = Ticket::where('resolved_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(CAST((JULIANDAY(resolved_at) - JULIANDAY(created_at)) * 24 AS REAL)) as avg_hours')
            ->value('avg_hours');

        return Inertia::render('Reports/Index', [
            'days' => $days,
            'volumeByDay' => $volumeByDay,
            'statusBreakdown' => $statusBreakdown,
            'priorityBreakdown' => $priorityBreakdown,
            'agentStats' => $agentStats,
            'sourceBreakdown' => $sourceBreakdown,
            'avgResolutionHours' => $avgResolutionHours ? round($avgResolutionHours, 1) : null,
        ]);
    }
}
