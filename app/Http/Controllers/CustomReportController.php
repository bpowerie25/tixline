<?php

namespace App\Http\Controllers;

use App\Models\CustomReport;
use App\Models\CustomReportWidget;
use App\Models\Label;
use App\Models\Team;
use App\Models\User;
use App\Services\WidgetDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomReportController extends Controller
{
    public function __construct(
        protected WidgetDataService $widgetDataService,
    ) {}

    public function index()
    {
        $user = auth()->user();

        $reports = CustomReport::where('user_id', $user->id)
            ->orWhere('is_shared', true)
            ->with('user:id,name')
            ->withCount('widgets')
            ->latest()
            ->get();

        return Inertia::render('Reports/Custom/Index', [
            'reports' => $reports,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $report = CustomReport::create([
            ...$validated,
            'user_id' => auth()->id(),
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return redirect()->route('custom-reports.show', $report);
    }

    public function show(CustomReport $report)
    {
        $this->authorizeAccess($report);

        $report->load('widgets');

        $widgetData = [];
        foreach ($report->widgets as $widget) {
            $widgetData[$widget->id] = $this->widgetDataService->getData(
                $widget->widget_type,
                $widget->filters ?? [],
                auth()->user(),
            );
        }

        return Inertia::render('Reports/Custom/Show', [
            'report' => $report,
            'widgetData' => $widgetData,
            'teams' => Team::all(['id', 'name']),
            'agents' => User::all(['id', 'name']),
            'labels' => Label::all(['id', 'name', 'color']),
        ]);
    }

    public function update(CustomReport $report, Request $request)
    {
        $this->authorizeAccess($report);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_shared' => 'boolean',
        ]);

        $report->update($validated);

        return back()->with('success', 'Report updated.');
    }

    public function destroy(CustomReport $report)
    {
        $this->authorizeAccess($report);

        $report->delete();

        return redirect()->route('custom-reports.index')->with('success', 'Report deleted.');
    }

    public function updateLayout(CustomReport $report, Request $request)
    {
        $this->authorizeAccess($report);

        $validated = $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|integer|exists:custom_report_widgets,id',
            'widgets.*.grid_x' => 'required|integer|min:0',
            'widgets.*.grid_y' => 'required|integer|min:0',
            'widgets.*.grid_w' => 'required|integer|min:1',
            'widgets.*.grid_h' => 'required|integer|min:1',
        ]);

        foreach ($validated['widgets'] as $widgetData) {
            CustomReportWidget::where('id', $widgetData['id'])
                ->where('custom_report_id', $report->id)
                ->update([
                    'grid_x' => $widgetData['grid_x'],
                    'grid_y' => $widgetData['grid_y'],
                    'grid_w' => $widgetData['grid_w'],
                    'grid_h' => $widgetData['grid_h'],
                ]);
        }

        return response()->json(['message' => 'Layout updated.']);
    }

    public function storeWidget(CustomReport $report, Request $request)
    {
        $this->authorizeAccess($report);

        $validated = $request->validate([
            'widget_type' => 'required|string',
            'chart_type' => 'required|string',
            'title' => 'required|string|max:255',
            'grid_x' => 'sometimes|integer|min:0',
            'grid_y' => 'sometimes|integer|min:0',
            'grid_w' => 'sometimes|integer|min:1',
            'grid_h' => 'sometimes|integer|min:1',
            'filters' => 'nullable|array',
        ]);

        // Default grid position: place below existing widgets, half-width
        $maxY = $report->widgets()->max('grid_y') ?? 0;
        $maxH = $report->widgets()->where('grid_y', $maxY)->max('grid_h') ?? 0;

        $widget = $report->widgets()->create(array_merge([
            'grid_x' => 0,
            'grid_y' => $maxY + $maxH,
            'grid_w' => 6,
            'grid_h' => 6,
        ], $validated));

        if ($request->wantsJson()) {
            return response()->json([
                'widget' => $widget,
                'data' => $this->widgetDataService->getData(
                    $widget->widget_type,
                    $widget->filters ?? [],
                    auth()->user(),
                ),
            ]);
        }

        return back()->with('success', 'Widget added.');
    }

    public function updateWidget(CustomReport $report, CustomReportWidget $widget, Request $request)
    {
        $this->authorizeAccess($report);
        $this->ensureWidgetBelongsToReport($report, $widget);

        $validated = $request->validate([
            'widget_type' => 'sometimes|required|string',
            'chart_type' => 'sometimes|required|string',
            'title' => 'sometimes|required|string|max:255',
            'grid_x' => 'sometimes|required|integer|min:0',
            'grid_y' => 'sometimes|required|integer|min:0',
            'grid_w' => 'sometimes|required|integer|min:1',
            'grid_h' => 'sometimes|required|integer|min:1',
            'filters' => 'nullable|array',
        ]);

        $widget->update($validated);

        return back()->with('success', 'Widget updated.');
    }

    public function destroyWidget(CustomReport $report, CustomReportWidget $widget)
    {
        $this->authorizeAccess($report);
        $this->ensureWidgetBelongsToReport($report, $widget);

        $widget->delete();

        return back()->with('success', 'Widget removed.');
    }

    public function widgetData(CustomReport $report, CustomReportWidget $widget)
    {
        $this->authorizeAccess($report);
        $this->ensureWidgetBelongsToReport($report, $widget);

        $data = $this->widgetDataService->getData(
            $widget->widget_type,
            $widget->filters ?? [],
            auth()->user(),
        );

        return response()->json($data);
    }

    public function export(CustomReport $report): StreamedResponse
    {
        $this->authorizeAccess($report);

        $report->load('widgets');

        $filename = str($report->name)->slug() . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            foreach ($report->widgets as $widget) {
                // Widget title as section header
                fputcsv($handle, [$widget->title]);

                $data = $this->widgetDataService->getData(
                    $widget->widget_type,
                    $widget->filters ?? [],
                    auth()->user(),
                );

                if (! empty($data)) {
                    if (isset($data['columns'], $data['rows'])) {
                        // Table widget (ticket_list)
                        fputcsv($handle, $data['columns']);
                        foreach ($data['rows'] as $row) {
                            fputcsv($handle, array_values($row));
                        }
                    } elseif (isset($data['labels'], $data['values'])) {
                        // Chart widget (bar, pie, line)
                        fputcsv($handle, ['Label', 'Value']);
                        foreach ($data['labels'] as $i => $label) {
                            fputcsv($handle, [$label, $data['values'][$i] ?? 0]);
                        }
                    } elseif (array_key_exists('value', $data)) {
                        // Number widget (avg_response_time, etc.)
                        fputcsv($handle, ['Value']);
                        fputcsv($handle, [$data['value'] ?? '-']);
                    }
                }

                // Blank separator row between widgets
                fputcsv($handle, []);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function authorizeAccess(CustomReport $report): void
    {
        $user = auth()->user();

        if ($report->user_id !== $user->id && ! $report->is_shared) {
            abort(403, 'You do not have access to this report.');
        }
    }

    protected function ensureWidgetBelongsToReport(CustomReport $report, CustomReportWidget $widget): void
    {
        if ($widget->custom_report_id !== $report->id) {
            abort(404);
        }
    }
}
