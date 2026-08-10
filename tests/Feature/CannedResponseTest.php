<?php

namespace Tests\Feature;

use App\Models\CannedResponse;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CannedResponseTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::AGENT)->first()->id]);
    }

    public function test_create_canned_response(): void
    {
        $this->actingAs($this->agent)
            ->post(route('canned-responses.store'), [
                'name' => 'Welcome',
                'shortcode' => 'welcome',
                'body' => 'Hi {{requester_name}}, thanks for reaching out!',
                'is_shared' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('canned_responses', [
            'shortcode' => 'welcome',
            'user_id' => $this->agent->id,
        ]);
    }

    public function test_shortcode_must_be_unique(): void
    {
        CannedResponse::create([
            'name' => 'Existing',
            'shortcode' => 'taken',
            'body' => 'Body',
            'user_id' => $this->agent->id,
        ]);

        $this->actingAs($this->agent)
            ->post(route('canned-responses.store'), [
                'name' => 'New',
                'shortcode' => 'taken',
                'body' => 'Body',
            ])
            ->assertSessionHasErrors('shortcode');
    }

    public function test_interpolate_variables(): void
    {
        $response = new CannedResponse([
            'body' => 'Hi {{requester_name}}, re: {{ticket_subject}} ({{ticket_reference}})',
        ]);

        $ticket = Ticket::create([
            'subject' => 'Login issue',
            'requester_name' => 'John',
            'requester_email' => 'john@test.com',
        ]);
        $ticket->updateQuietly(['reference' => 'TKT-000001']);

        $result = $response->interpolate($ticket);

        $this->assertStringContainsString('Hi John', $result);
        $this->assertStringContainsString('Login issue', $result);
        $this->assertStringContainsString('TKT-000001', $result);
    }

    public function test_delete_canned_response(): void
    {
        $response = CannedResponse::create([
            'name' => 'Delete me',
            'shortcode' => 'delete',
            'body' => 'Body',
            'user_id' => $this->agent->id,
        ]);

        $this->actingAs($this->agent)
            ->delete(route('canned-responses.destroy', $response))
            ->assertRedirect();

        $this->assertDatabaseMissing('canned_responses', ['id' => $response->id]);
    }
}
