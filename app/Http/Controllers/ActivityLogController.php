<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('user:id,name,email')
            ->search($request->search)
            ->forAction($request->action)
            ->forUser($request->user_id)
            ->when($request->date_from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString();

        $actions = ActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'action', 'user_id', 'date_from', 'date_to']),
            'actions' => $actions,
            'agents' => User::all(['id', 'name']),
        ]);
    }
}
