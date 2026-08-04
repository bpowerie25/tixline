<?php

namespace Tests\Unit;

use App\Models\Comment;
use App\Models\Ticket;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AttachmentService $service;

    protected Comment $comment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttachmentService;
        Storage::fake('local');

        $ticket = Ticket::create([
            'subject' => 'Test',
            'requester_name' => 'Test',
            'requester_email' => 'test@example.com',
        ]);
        $this->comment = $ticket->comments()->create([
            'body' => 'Test comment',
            'type' => 'reply',
        ]);
    }

    public function test_stores_allowed_file(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $attachment = $this->service->storeUploadedFile($file, $this->comment);

        $this->assertNotNull($attachment);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_rejects_disallowed_mime(): void
    {
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $attachment = $this->service->storeUploadedFile($file, $this->comment);

        $this->assertNull($attachment);
    }

    public function test_rejects_oversized_file(): void
    {
        config(['support.attachments.max_size_bytes' => 1024]); // 1KB

        $file = UploadedFile::fake()->create('big.pdf', 2000, 'application/pdf'); // 2MB

        $attachment = $this->service->storeUploadedFile($file, $this->comment);

        $this->assertNull($attachment);
    }

    public function test_sanitizes_path_traversal_filename(): void
    {
        $file = UploadedFile::fake()->create('../../etc/passwd', 10, 'text/plain');

        $attachment = $this->service->storeUploadedFile($file, $this->comment);

        $this->assertNotNull($attachment);
        // Original filename should not contain path traversal
        $this->assertStringNotContainsString('..', $attachment->original_filename);
        $this->assertStringNotContainsString('/', $attachment->original_filename);
    }

    public function test_strips_executable_extension(): void
    {
        $file = UploadedFile::fake()->create('payload.php', 10, 'text/plain');

        $attachment = $this->service->storeUploadedFile($file, $this->comment);

        $this->assertNotNull($attachment);
        // The stored filename should not end in .php
        $this->assertStringNotContainsString('.php', $attachment->filename);
    }

    public function test_stores_webhook_attachment(): void
    {
        $content = base64_encode('Hello World PDF content');

        $attachment = $this->service->storeFromWebhook([
            'filename' => 'report.pdf',
            'content_type' => 'application/pdf',
            'content' => $content,
        ], $this->comment);

        $this->assertNotNull($attachment);
        $this->assertEquals('application/pdf', $attachment->mime_type);
        $this->assertEquals('report.pdf', $attachment->original_filename);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_webhook_rejects_disallowed_mime(): void
    {
        $attachment = $this->service->storeFromWebhook([
            'filename' => 'virus.exe',
            'content_type' => 'application/x-msdownload',
            'content' => base64_encode('bad'),
        ], $this->comment);

        $this->assertNull($attachment);
    }

    public function test_webhook_rejects_oversized(): void
    {
        config(['support.attachments.max_size_bytes' => 10]);

        $attachment = $this->service->storeFromWebhook([
            'filename' => 'big.pdf',
            'content_type' => 'application/pdf',
            'content' => base64_encode(str_repeat('x', 100)),
        ], $this->comment);

        $this->assertNull($attachment);
    }

    public function test_webhook_sanitizes_traversal_filename(): void
    {
        $attachment = $this->service->storeFromWebhook([
            'filename' => '../../../etc/shadow',
            'content_type' => 'text/plain',
            'content' => base64_encode('test'),
        ], $this->comment);

        $this->assertNotNull($attachment);
        $this->assertStringNotContainsString('..', $attachment->original_filename);
        // Filename portion (not path) should not contain traversal
        $this->assertStringNotContainsString('..', $attachment->filename);
    }

    public function test_rejects_invalid_base64(): void
    {
        $attachment = $this->service->storeFromWebhook([
            'filename' => 'file.pdf',
            'content_type' => 'application/pdf',
            'content' => '!!!not-base64!!!',
        ], $this->comment);

        $this->assertNull($attachment);
    }

    public function test_never_stores_to_public_disk_by_default(): void
    {
        $this->assertEquals('local', config('support.attachments.disk'));
    }
}
