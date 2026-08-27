<?php

namespace App\Services;

use App\Models\BusinessHours;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Turns SLA targets expressed in hours into wall-clock deadlines that only
 * advance while the business is open.
 *
 * With no schedule (or a schedule that is closed every day of the week) it
 * degrades to plain round-the-clock arithmetic, which is what the SLA
 * calculation did before business hours existed.
 */
class BusinessHoursCalculator
{
    /**
     * Backstop for the calendar walk. A schedule with at least one open window
     * a week cannot need more than a handful of days per SLA hour, so hitting
     * this means the schedule is pathological (e.g. holidays covering every
     * open day for years) and we fall back rather than spin.
     */
    protected const MAX_DAYS = 1000;

    protected ?BusinessHours $schedule;

    public function __construct(?BusinessHours $schedule = null)
    {
        $this->schedule = $schedule?->hasOpenWindows() ? $schedule : null;
    }

    public static function for(?BusinessHours $schedule): self
    {
        return new self($schedule);
    }

    /**
     * True when no usable schedule is configured and the clock runs 24/7.
     */
    public function isRoundTheClock(): bool
    {
        return $this->schedule === null;
    }

    /**
     * The moment `$hours` of open time after `$from` has elapsed.
     *
     * A ticket that arrives outside opening hours does not start its clock
     * until the business next opens.
     */
    public function dueAt(CarbonInterface $from, float $hours): Carbon
    {
        $remaining = (int) round($hours * 3600);
        $origin = Carbon::instance($from)->copy();

        if ($this->isRoundTheClock() || $remaining <= 0) {
            return $origin->copy()->addSeconds(max($remaining, 0));
        }

        $timezone = $origin->getTimezone();
        $cursor = $origin->copy()->setTimezone($this->schedule->timezone);
        $holidays = $this->schedule->holidayDates();

        for ($day = 0; $day < self::MAX_DAYS; $day++) {
            $date = $cursor->copy()->startOfDay();

            if (! isset($holidays[$date->format('Y-m-d')])) {
                foreach ($this->schedule->windowsFor($date->dayOfWeekIso) as $window) {
                    [$open, $close] = $this->windowBounds($date, $window);

                    if ($close->lessThanOrEqualTo($cursor)) {
                        continue;
                    }

                    $enter = $cursor->greaterThan($open) ? $cursor->copy() : $open;
                    $available = (int) $enter->diffInSeconds($close);

                    if ($remaining <= $available) {
                        return $enter->addSeconds($remaining)->setTimezone($timezone);
                    }

                    $remaining -= $available;
                }
            }

            $cursor = $date->addDay();
        }

        return $origin->copy()->addSeconds($remaining);
    }

    /**
     * Open minutes between two moments — the counterpart of dueAt(), used to
     * judge how far through an SLA a ticket is.
     */
    public function elapsedMinutes(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = Carbon::instance($from)->copy();
        $end = Carbon::instance($to)->copy();

        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }

        if ($this->isRoundTheClock()) {
            return (int) $start->diffInMinutes($end);
        }

        $cursor = $start->setTimezone($this->schedule->timezone);
        $end = $end->setTimezone($this->schedule->timezone);
        $holidays = $this->schedule->holidayDates();
        $seconds = 0;
        $day = 0;

        while ($cursor->lessThan($end) && $day++ < self::MAX_DAYS) {
            $date = $cursor->copy()->startOfDay();

            if (! isset($holidays[$date->format('Y-m-d')])) {
                foreach ($this->schedule->windowsFor($date->dayOfWeekIso) as $window) {
                    [$open, $close] = $this->windowBounds($date, $window);

                    $enter = $cursor->greaterThan($open) ? $cursor->copy() : $open;
                    $leave = $end->lessThan($close) ? $end->copy() : $close;

                    if ($leave->greaterThan($enter)) {
                        $seconds += (int) $enter->diffInSeconds($leave);
                    }
                }
            }

            $cursor = $date->addDay();
        }

        return intdiv($seconds, 60);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function windowBounds(Carbon $date, array $window): array
    {
        return [
            $this->atTime($date, $window['start']),
            $this->atTime($date, $window['end']),
        ];
    }

    protected function atTime(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        // setTime(24, 0) rolls to midnight the following day, which is how a
        // window that closes at the end of the day is expressed.
        return $date->copy()->setTime((int) $hour, (int) $minute, 0);
    }
}
