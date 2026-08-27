<?php

namespace App\Http\Controllers;

use App\Models\BusinessHours;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BusinessHoursController extends Controller
{
    /**
     * HH:MM, plus 24:00 for a window that runs to the end of the day.
     */
    protected const TIME_PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d$|^24:00$/';

    public function index()
    {
        $schedule = BusinessHours::first();

        return Inertia::render('Settings/BusinessHours', [
            'schedule' => $schedule ? [
                'timezone' => $schedule->timezone,
                'days' => $this->normalizeDaysForDisplay($schedule),
                'holidays' => array_values($schedule->holidays ?? []),
            ] : null,
            'defaults' => [
                'timezone' => config('app.timezone') ?: 'UTC',
                'days' => $this->defaultDays(),
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'days' => ['required', 'array'],
            'days.*' => ['array'],
            'days.*.*.start' => ['required', 'string', 'regex:'.self::TIME_PATTERN],
            'days.*.*.end' => ['required', 'string', 'regex:'.self::TIME_PATTERN],
            'holidays' => ['nullable', 'array'],
            'holidays.*.date' => ['required', 'date_format:Y-m-d'],
            'holidays.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        $days = $this->validateDays($request, $validated['days']);

        $schedule = BusinessHours::first() ?? new BusinessHours;

        $schedule->fill([
            'name' => $schedule->name ?: 'Business Hours',
            'timezone' => $validated['timezone'],
            'days' => $days,
            'holidays' => array_values($validated['holidays'] ?? []),
        ])->save();

        return back()->with('success', 'Business hours updated.');
    }

    public function destroy()
    {
        BusinessHours::first()?->delete();

        return back()->with('success', 'Business hours removed. SLA targets now run around the clock.');
    }

    /**
     * Reject days whose windows are inverted or overlapping — either would
     * make an SLA deadline meaningless, and neither is expressible in the UI,
     * so it can only arrive from a hand-rolled request.
     */
    protected function validateDays(Request $request, array $days): array
    {
        $clean = [];

        foreach ($days as $weekday => $windows) {
            if (! in_array((int) $weekday, range(1, 7), true)) {
                $this->fail('Business hours must be keyed by ISO weekday (1-7).');
            }

            $windows = array_values(array_map(
                fn ($window) => ['start' => $window['start'], 'end' => $window['end']],
                $windows,
            ));

            usort($windows, fn ($a, $b) => strcmp($a['start'], $b['start']));

            $previousEnd = null;

            foreach ($windows as $window) {
                if ($window['end'] <= $window['start']) {
                    $this->fail("A closing time must be after its opening time on {$this->dayName($weekday)}.");
                }

                if ($previousEnd !== null && $window['start'] < $previousEnd) {
                    $this->fail("Opening hours on {$this->dayName($weekday)} must not overlap.");
                }

                $previousEnd = $window['end'];
            }

            if ($windows !== []) {
                $clean[(string) (int) $weekday] = $windows;
            }
        }

        return $clean;
    }

    /**
     * Reported against `days` so the form shows it, rather than as a bare 422
     * the page cannot render: two overlapping windows on one day is a state
     * the UI can reach.
     */
    protected function fail(string $message): never
    {
        throw ValidationException::withMessages(['days' => $message]);
    }

    protected function dayName(int|string $isoWeekday): string
    {
        return [
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
        ][(int) $isoWeekday] ?? 'that day';
    }

    protected function normalizeDaysForDisplay(BusinessHours $schedule): array
    {
        $days = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $days[(string) $weekday] = $schedule->windowsFor($weekday);
        }

        return $days;
    }

    protected function defaultDays(): array
    {
        $days = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $days[(string) $weekday] = $weekday <= 5
                ? [['start' => '09:00', 'end' => '17:00']]
                : [];
        }

        return $days;
    }
}
