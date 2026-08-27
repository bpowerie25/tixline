<?php

namespace Tests\Unit;

use App\Models\BusinessHours;
use App\Services\BusinessHoursCalculator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessHoursCalculatorTest extends TestCase
{
    /**
     * Mon-Fri, 09:00-17:00 UTC. Not persisted: the calculator only reads the
     * schedule's attributes.
     */
    protected function weekdaySchedule(array $overrides = []): BusinessHours
    {
        return new BusinessHours(array_merge([
            'timezone' => 'UTC',
            'days' => [
                '1' => [['start' => '09:00', 'end' => '17:00']],
                '2' => [['start' => '09:00', 'end' => '17:00']],
                '3' => [['start' => '09:00', 'end' => '17:00']],
                '4' => [['start' => '09:00', 'end' => '17:00']],
                '5' => [['start' => '09:00', 'end' => '17:00']],
                '6' => [],
                '7' => [],
            ],
            'holidays' => [],
        ], $overrides));
    }

    public function test_falls_back_to_round_the_clock_without_a_schedule(): void
    {
        $calculator = BusinessHoursCalculator::for(null);

        $this->assertTrue($calculator->isRoundTheClock());
        $this->assertEquals(
            '2026-08-28 14:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-28 10:00:00'), 4)->toDateTimeString(),
        );
    }

    public function test_a_schedule_with_no_open_days_is_treated_as_unconfigured(): void
    {
        $schedule = $this->weekdaySchedule(['days' => ['1' => [], '2' => []]]);

        $this->assertTrue(BusinessHoursCalculator::for($schedule)->isRoundTheClock());
    }

    public function test_deadline_inside_one_working_day(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        // Wednesday 10:00 + 4h = Wednesday 14:00
        $this->assertEquals(
            '2026-08-26 14:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 10:00:00'), 4)->toDateTimeString(),
        );
    }

    public function test_deadline_rolls_over_to_the_next_working_day(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        // Wednesday 15:00 + 4h: 2h left today, 2h from Thursday 09:00
        $this->assertEquals(
            '2026-08-27 11:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 15:00:00'), 4)->toDateTimeString(),
        );
    }

    /**
     * The case the old wall-clock arithmetic got wrong: a Friday evening
     * ticket used to breach a 4h target before Saturday lunchtime.
     */
    public function test_friday_evening_ticket_is_due_monday_morning(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        // Friday 18:00 is outside hours, so the clock starts Monday 09:00
        $this->assertEquals(
            '2026-08-31 13:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-28 18:00:00'), 4)->toDateTimeString(),
        );
    }

    public function test_ticket_arriving_before_opening_starts_at_opening(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        $this->assertEquals(
            '2026-08-26 11:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 06:30:00'), 2)->toDateTimeString(),
        );
    }

    public function test_holidays_are_skipped(): void
    {
        $schedule = $this->weekdaySchedule([
            'holidays' => [['date' => '2026-08-27', 'name' => 'Company day off']],
        ]);

        $calculator = BusinessHoursCalculator::for($schedule);

        // Wednesday 15:00 + 4h, Thursday closed, so it lands Friday 11:00
        $this->assertEquals(
            '2026-08-28 11:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 15:00:00'), 4)->toDateTimeString(),
        );
    }

    public function test_split_days_skip_the_lunch_break(): void
    {
        $schedule = $this->weekdaySchedule([
            'days' => [
                '3' => [
                    ['start' => '09:00', 'end' => '12:00'],
                    ['start' => '13:00', 'end' => '17:00'],
                ],
            ],
        ]);

        $calculator = BusinessHoursCalculator::for($schedule);

        // 11:00 + 2h: one hour until noon, the rest from 13:00
        $this->assertEquals(
            '2026-08-26 14:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 11:00:00'), 2)->toDateTimeString(),
        );
    }

    public function test_deadline_respects_the_schedule_timezone(): void
    {
        $schedule = $this->weekdaySchedule(['timezone' => 'America/New_York']);
        $calculator = BusinessHoursCalculator::for($schedule);

        // 13:00 UTC on a Wednesday is 09:00 in New York, so +4h is 13:00 local
        $this->assertEquals(
            '2026-08-26 17:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 13:00:00'), 4)->toDateTimeString(),
        );
    }

    public function test_elapsed_minutes_counts_only_open_time(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        // Friday 16:00 to Monday 10:00 is one open hour on Friday plus one on Monday
        $this->assertEquals(
            120,
            $calculator->elapsedMinutes(
                Carbon::parse('2026-08-28 16:00:00'),
                Carbon::parse('2026-08-31 10:00:00'),
            ),
        );
    }

    public function test_elapsed_minutes_is_zero_when_the_range_is_closed(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        $this->assertEquals(0, $calculator->elapsedMinutes(
            Carbon::parse('2026-08-29 10:00:00'),
            Carbon::parse('2026-08-30 10:00:00'),
        ));
    }

    public function test_elapsed_minutes_is_zero_for_an_inverted_range(): void
    {
        $calculator = BusinessHoursCalculator::for($this->weekdaySchedule());

        $this->assertEquals(0, $calculator->elapsedMinutes(
            Carbon::parse('2026-08-26 15:00:00'),
            Carbon::parse('2026-08-26 10:00:00'),
        ));
    }

    public function test_a_window_closing_at_midnight_is_honoured(): void
    {
        $schedule = $this->weekdaySchedule([
            'days' => ['3' => [['start' => '22:00', 'end' => '24:00']]],
        ]);

        $calculator = BusinessHoursCalculator::for($schedule);

        $this->assertEquals(
            '2026-08-26 23:00:00',
            $calculator->dueAt(Carbon::parse('2026-08-26 22:00:00'), 1)->toDateTimeString(),
        );
    }
}
