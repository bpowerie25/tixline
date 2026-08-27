<?php

namespace Tests\Feature;

use App\Models\BusinessHours;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BusinessHoursTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::where('name', Role::ADMIN)->first()->id,
        ]);
    }

    protected function weekdayHours(): BusinessHours
    {
        return BusinessHours::create([
            'timezone' => 'UTC',
            'days' => [
                '1' => [['start' => '09:00', 'end' => '17:00']],
                '2' => [['start' => '09:00', 'end' => '17:00']],
                '3' => [['start' => '09:00', 'end' => '17:00']],
                '4' => [['start' => '09:00', 'end' => '17:00']],
                '5' => [['start' => '09:00', 'end' => '17:00']],
            ],
            'holidays' => [],
        ]);
    }

    protected function urgentPolicy(array $overrides = []): SlaPolicy
    {
        return SlaPolicy::create(array_merge([
            'name' => 'Urgent',
            'priority' => 'urgent',
            'first_response_hours' => 4,
            'resolution_hours' => 8,
            'is_active' => true,
        ], $overrides));
    }

    public function test_a_friday_evening_ticket_is_not_due_over_the_weekend(): void
    {
        $this->weekdayHours();
        $this->urgentPolicy();

        Carbon::setTestNow('2026-08-28 18:00:00'); // Friday evening

        $ticket = Ticket::create([
            'subject' => 'After hours',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ])->fresh();

        // 4 business hours from Monday 09:00
        $this->assertEquals('2026-08-31 13:00:00', $ticket->sla_response_due_at->toDateTimeString());
        // 8 business hours lands at Monday close of play
        $this->assertEquals('2026-08-31 17:00:00', $ticket->sla_resolution_due_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_a_policy_can_opt_out_of_business_hours(): void
    {
        $this->weekdayHours();
        $this->urgentPolicy(['use_business_hours' => false]);

        Carbon::setTestNow('2026-08-28 18:00:00');

        $ticket = Ticket::create([
            'subject' => 'Round the clock',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ])->fresh();

        $this->assertEquals('2026-08-28 22:00:00', $ticket->sla_response_due_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_without_a_schedule_targets_run_around_the_clock(): void
    {
        $this->urgentPolicy();

        Carbon::setTestNow('2026-08-28 18:00:00');

        $ticket = Ticket::create([
            'subject' => 'No schedule',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ])->fresh();

        $this->assertEquals('2026-08-28 22:00:00', $ticket->sla_response_due_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_the_warning_moment_is_stamped_at_seventy_five_percent(): void
    {
        $this->weekdayHours();
        $this->urgentPolicy();

        Carbon::setTestNow('2026-08-26 09:00:00'); // Wednesday, at opening

        $ticket = Ticket::create([
            'subject' => 'Warning point',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ])->fresh();

        // 75% of an 8 business-hour resolution target is 6 business hours
        $this->assertEquals('2026-08-26 15:00:00', $ticket->sla_warning_at->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_sla_status_does_not_warn_while_the_business_is_closed(): void
    {
        $this->weekdayHours();
        $this->urgentPolicy();

        Carbon::setTestNow('2026-08-28 16:00:00'); // Friday, an hour before close

        $ticket = Ticket::create([
            'subject' => 'Weekend',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ])->fresh();

        // Saturday afternoon: the old wall-clock maths called this breached
        Carbon::setTestNow('2026-08-29 14:00:00');
        $this->assertEquals('on_track', $ticket->fresh()->sla_status);

        Carbon::setTestNow();
    }

    public function test_the_breach_command_does_not_fire_over_the_weekend(): void
    {
        $this->weekdayHours();
        $this->urgentPolicy();

        Workflow::create([
            'name' => 'Escalate',
            'trigger_event' => 'sla_response_breached',
            'events' => [['entity' => 'ticket', 'action' => 'sla_response_breached']],
            'conditions' => ['match' => 'all', 'rules' => []],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'is_active' => true,
            'priority' => 0,
        ]);

        Carbon::setTestNow('2026-08-28 16:00:00'); // Friday
        Ticket::create([
            'subject' => 'Weekend safe',
            'requester_name' => 'A',
            'requester_email' => 'a@test.com',
            'priority' => 'urgent',
        ]);

        Carbon::setTestNow('2026-08-29 23:00:00'); // Saturday night
        $this->artisan('support:check-sla')->assertSuccessful();

        $this->assertDatabaseHas('tickets', [
            'subject' => 'Weekend safe',
            'sla_response_breach_fired' => false,
            'sla_warning_fired' => false,
        ]);

        Carbon::setTestNow();
    }

    public function test_an_admin_can_save_and_clear_business_hours(): void
    {
        $this->actingAs($this->admin)
            ->post(route('business-hours.store'), [
                'timezone' => 'Europe/Dublin',
                'days' => [
                    '1' => [['start' => '09:00', 'end' => '17:00']],
                    '6' => [],
                ],
                'holidays' => [['date' => '2026-12-25', 'name' => 'Christmas']],
            ])
            ->assertRedirect();

        $schedule = BusinessHours::first();
        $this->assertEquals('Europe/Dublin', $schedule->timezone);
        $this->assertEquals([['start' => '09:00', 'end' => '17:00']], $schedule->windowsFor(1));
        $this->assertEquals([], $schedule->windowsFor(6));

        $this->actingAs($this->admin)
            ->delete(route('business-hours.destroy'))
            ->assertRedirect();

        $this->assertNull(BusinessHours::first());
    }

    public function test_saving_twice_updates_rather_than_duplicates(): void
    {
        $payload = [
            'timezone' => 'UTC',
            'days' => ['1' => [['start' => '09:00', 'end' => '17:00']]],
        ];

        $this->actingAs($this->admin)->post(route('business-hours.store'), $payload);
        $this->actingAs($this->admin)->post(route('business-hours.store'), array_merge($payload, [
            'timezone' => 'Europe/Dublin',
        ]));

        $this->assertEquals(1, BusinessHours::count());
        $this->assertEquals('Europe/Dublin', BusinessHours::first()->timezone);
    }

    public function test_overlapping_windows_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('business-hours.store'), [
                'timezone' => 'UTC',
                'days' => ['1' => [
                    ['start' => '09:00', 'end' => '13:00'],
                    ['start' => '12:00', 'end' => '17:00'],
                ]],
            ])
            ->assertSessionHasErrors('days');

        $this->assertNull(BusinessHours::first());
    }

    public function test_a_closing_time_before_its_opening_time_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('business-hours.store'), [
                'timezone' => 'UTC',
                'days' => ['1' => [['start' => '17:00', 'end' => '09:00']]],
            ])
            ->assertSessionHasErrors('days');

        $this->assertNull(BusinessHours::first());
    }

    public function test_an_agent_without_the_permission_cannot_change_business_hours(): void
    {
        $agent = User::factory()->create([
            'role_id' => Role::where('name', Role::AGENT)->first()->id,
        ]);

        $this->actingAs($agent)->get(route('business-hours.index'))->assertForbidden();
    }
}
