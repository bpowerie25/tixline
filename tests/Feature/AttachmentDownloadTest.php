<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function createTicketWithAttachment(array $ticketOverrides = []): array
    {
        $ticket = Ticket::create(array_merge([
            'subject' => 'Test ticket',
            'requester_name' => 'Customer',
            'requester_email' => 'customer@example.com',
        ], $ticketOverrides));

        Storage::disk('local')->put('attachments/test/file.html', '<script>alert(1)</script>');

        $attachment = $ticket->attachments()->create([
            'filename' => 'abc123.html',
            'original_filename' => 'report.html',
            'mime_type' => 'text/html',
            'size' => 100,
            'path' => 'attachments/test/file.html',
        ]);

        return [$ticket, $attachment];
    }

    public function test_unauthenticated_user_cannot_download(): void
    {
        [$ticket, $attachment] = $this->createTicketWithAttachment();

        $this->get(route('attachments.download', $attachment))
            ->assertUnauthorized();
    }

    public function test_agent_can_download_visible_ticket_attachment(): void
    {
        $agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);
        [$ticket, $attachment] = $this->createTicketWithAttachment();

        $response = $this->actingAs($agent)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_agent_cannot_download_attachment_on_invisible_ticket(): void
    {
        $teamA = Team::create(['name' => 'Team A', 'slug' => 'team-a']);
        $teamB = Team::create(['name' => 'Team B', 'slug' => 'team-b']);
        $agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::AGENT)->first()->id, 'team_id' => $teamA->id]);

        [$ticket, $attachment] = $this->createTicketWithAttachment([
            'team_id' => $teamB->id,
            'assigned_to' => null,
        ]);

        $this->actingAs($agent)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_customer_can_download_own_ticket_attachment(): void
    {
        $customer = Customer::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
        ]);

        [$ticket, $attachment] = $this->createTicketWithAttachment([
            'requester_email' => 'customer@example.com',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_customer_cannot_download_other_ticket_attachment(): void
    {
        $customer = Customer::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
        ]);

        [$ticket, $attachment] = $this->createTicketWithAttachment([
            'requester_email' => 'bob@example.com',
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_html_attachment_served_as_octet_stream(): void
    {
        $agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);
        [$ticket, $attachment] = $this->createTicketWithAttachment();

        $response = $this->actingAs($agent)
            ->get(route('attachments.download', $attachment));

        // Must NOT serve as text/html — that would allow XSS
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_svg_attachment_served_as_download(): void
    {
        $agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        Storage::disk('local')->put('attachments/test/evil.svg', '<svg onload="alert(1)">');

        $attachment = $ticket->attachments()->create([
            'filename' => 'safe.svg',
            'original_filename' => 'diagram.svg',
            'mime_type' => 'image/svg+xml',
            'size' => 50,
            'path' => 'attachments/test/evil.svg',
        ]);

        $response = $this->actingAs($agent)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_comment_attachment_authorized_through_ticket(): void
    {
        $agent = User::factory()->create(['role_id' => \App\Models\Role::where('name', \App\Models\Role::ADMIN)->first()->id]);
        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);

        $comment = $ticket->comments()->create([
            'body' => 'Reply',
            'type' => 'reply',
            'is_internal' => false,
        ]);

        Storage::disk('local')->put('attachments/test/doc.pdf', 'pdf-content');

        $attachment = $comment->attachments()->create([
            'filename' => 'doc.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
            'path' => 'attachments/test/doc.pdf',
        ]);

        $response = $this->actingAs($agent)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
    }
}
