<?php

namespace Tests\Feature;

use App\Jobs\ProcessInboundEmailJob;
use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    public function test_env_example_has_database_queue(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString(
            'QUEUE_CONNECTION=database',
            $envExample,
            '.env.example must default to database queue, not sync'
        );
    }

    public function test_inbound_email_job_has_retry_config(): void
    {
        $job = new ProcessInboundEmailJob(1);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([30, 120, 300], $job->backoff);
    }

    public function test_config_default_is_database_not_sync(): void
    {
        // Read the raw PHP config to check the env() fallback value
        $configSource = file_get_contents(base_path('config/queue.php'));

        // The fallback in env('QUEUE_CONNECTION', '...') must be 'database'
        $this->assertMatchesRegularExpression(
            "/env\(\s*'QUEUE_CONNECTION'\s*,\s*'database'\s*\)/",
            $configSource,
            'config/queue.php must default to database when QUEUE_CONNECTION env is unset'
        );
    }
}
