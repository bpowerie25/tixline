<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Models\User;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRegexTest extends TestCase
{
    use RefreshDatabase;

    protected WorkflowEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(WorkflowEngine::class);
    }

    public function test_valid_regex_matches(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Invoice #12345',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $result = $this->engine->evaluateConditionsPublic($ticket, [
            'match' => 'all',
            'rules' => [
                ['field' => 'subject', 'operator' => 'matches_regex', 'value' => 'Invoice #\d+'],
            ],
        ]);

        $this->assertTrue($result);
    }

    public function test_invalid_regex_returns_false_not_error(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        // Invalid pattern with unbalanced parenthesis
        $result = $this->engine->evaluateConditionsPublic($ticket, [
            'match' => 'all',
            'rules' => [
                ['field' => 'subject', 'operator' => 'matches_regex', 'value' => '(unclosed'],
            ],
        ]);

        $this->assertFalse($result);
    }

    public function test_delimiter_in_pattern_does_not_break(): void
    {
        $ticket = Ticket::create([
            'subject' => 'path/to/file',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        // Pattern containing forward slashes (old delimiter)
        $result = $this->engine->evaluateConditionsPublic($ticket, [
            'match' => 'all',
            'rules' => [
                ['field' => 'subject', 'operator' => 'matches_regex', 'value' => 'path/to/file'],
            ],
        ]);

        $this->assertTrue($result);
    }

    public function test_tilde_delimiter_in_pattern_is_escaped(): void
    {
        $ticket = Ticket::create([
            'subject' => 'test~value',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $result = $this->engine->evaluateConditionsPublic($ticket, [
            'match' => 'all',
            'rules' => [
                ['field' => 'subject', 'operator' => 'matches_regex', 'value' => 'test~value'],
            ],
        ]);

        $this->assertTrue($result);
    }

    public function test_empty_regex_returns_false(): void
    {
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $result = $this->engine->evaluateConditionsPublic($ticket, [
            'match' => 'all',
            'rules' => [
                ['field' => 'subject', 'operator' => 'matches_regex', 'value' => ''],
            ],
        ]);

        $this->assertFalse($result);
    }

    public function test_is_valid_regex_accepts_good_pattern(): void
    {
        $this->assertTrue(WorkflowEngine::isValidRegex('\d+'));
        $this->assertTrue(WorkflowEngine::isValidRegex('foo|bar'));
        $this->assertTrue(WorkflowEngine::isValidRegex('^start'));
    }

    public function test_is_valid_regex_rejects_bad_pattern(): void
    {
        $this->assertFalse(WorkflowEngine::isValidRegex('(unclosed'));
        $this->assertFalse(WorkflowEngine::isValidRegex('[invalid'));
        $this->assertFalse(WorkflowEngine::isValidRegex(''));
    }

    public function test_workflow_save_rejects_invalid_regex(): void
    {
        $admin = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);

        $response = $this->actingAs($admin)->post(route('workflows.store'), [
            'name' => 'Bad regex workflow',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'all',
                'rules' => [
                    ['field' => 'subject', 'operator' => 'matches_regex', 'value' => '(unclosed'],
                ],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'high']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $response->assertSessionHasErrors('conditions');
        $this->assertEquals(0, Workflow::count());
    }

    public function test_workflow_save_accepts_valid_regex(): void
    {
        $admin = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);

        $response = $this->actingAs($admin)->post(route('workflows.store'), [
            'name' => 'Good regex workflow',
            'trigger_event' => 'ticket_created',
            'conditions' => [
                'match' => 'all',
                'rules' => [
                    ['field' => 'subject', 'operator' => 'matches_regex', 'value' => 'Invoice #\d+'],
                ],
            ],
            'actions' => [['type' => 'set_priority', 'value' => 'high']],
            'is_active' => true,
            'priority' => 0,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals(1, Workflow::count());
    }
}
