<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's working week, used to stop SLA clocks outside opening hours.
 */
class BusinessHours extends Model
{
    use BelongsToTenant;

    protected $table = 'business_hours';

    protected $fillable = ['name', 'timezone', 'days', 'holidays', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'holidays' => 'array',
        ];
    }

    /**
     * The schedule belonging to a specific tenant.
     *
     * Deliberately bypasses the global tenant scope and matches on the id it
     * is given: SLA deadlines are calculated from the console and from inbound
     * mail, where the request-bound tenant is not the ticket's tenant (or is
     * not bound at all). A null id means the single-tenant install, whose rows
     * carry a null tenant_id — `where(..., null)` would never match those.
     */
    public static function forTenant(?int $tenantId): ?self
    {
        return static::withoutGlobalScope(TenantScope::class)
            ->when(
                $tenantId === null,
                fn ($query) => $query->whereNull('tenant_id'),
                fn ($query) => $query->where('tenant_id', $tenantId),
            )
            ->first();
    }

    /**
     * Opening windows for an ISO weekday (1 = Monday ... 7 = Sunday), as a
     * list of ['start' => 'HH:MM', 'end' => 'HH:MM'] sorted by start time.
     */
    public function windowsFor(int $isoWeekday): array
    {
        $windows = $this->days[(string) $isoWeekday] ?? $this->days[$isoWeekday] ?? [];

        if (! is_array($windows)) {
            return [];
        }

        $windows = array_values(array_filter(
            $windows,
            fn ($window) => is_array($window) && isset($window['start'], $window['end']),
        ));

        usort($windows, fn ($a, $b) => strcmp($a['start'], $b['start']));

        return $windows;
    }

    /**
     * Closure dates as a set of Y-m-d strings, for O(1) lookup while walking
     * the calendar.
     */
    public function holidayDates(): array
    {
        $dates = [];

        foreach ($this->holidays ?? [] as $holiday) {
            $date = is_array($holiday) ? ($holiday['date'] ?? null) : $holiday;

            if ($date) {
                $dates[$date] = true;
            }
        }

        return $dates;
    }

    /**
     * Whether the week has any open time in it at all. A schedule with every
     * day closed would make an SLA deadline unreachable, so callers treat it
     * as "not configured" rather than looping forever looking for an opening.
     */
    public function hasOpenWindows(): bool
    {
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            if ($this->windowsFor($weekday) !== []) {
                return true;
            }
        }

        return false;
    }
}
