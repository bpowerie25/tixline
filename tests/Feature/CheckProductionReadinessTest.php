<?php

namespace Tests\Feature;

use Tests\TestCase;

class CheckProductionReadinessTest extends TestCase
{
    public function test_fails_with_debug_enabled(): void
    {
        config(['app.debug' => true]);

        $this->artisan('support:check-production')
            ->assertFailed();
    }

    public function test_passes_with_production_config(): void
    {
        config([
            'app.debug' => false,
            'app.env' => 'production',
            'app.url' => 'https://support.digital4business.eu',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'mail.default' => 'array',
            'support.inbound.webhook_secret' => 'a-real-secret',
            'queue.default' => 'database',
        ]);

        // LOG_LEVEL check reads from config — set it
        config(['logging.channels.single.level' => 'warning']);

        $this->artisan('support:check-production')
            ->assertSuccessful();
    }

    public function test_fails_with_log_mailer(): void
    {
        config([
            'app.debug' => false,
            'app.env' => 'production',
            'app.url' => 'https://example.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'mail.default' => 'log',
            'support.inbound.webhook_secret' => 'secret',
            'queue.default' => 'database',
            'logging.channels.single.level' => 'warning',
        ]);

        $this->artisan('support:check-production')
            ->assertFailed();
    }

    public function test_fails_with_empty_webhook_secret(): void
    {
        config([
            'app.debug' => false,
            'app.env' => 'production',
            'app.url' => 'https://example.com',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'session.secure' => true,
            'mail.default' => 'array',
            // The check accepts either credential, so clearing one proves
            // nothing unless the other is cleared too. Left set, this test
            // passed only where no Mailgun key happened to be configured --
            // it failed the moment the suite was pointed at the cloud app.
            'support.inbound.webhook_secret' => '',
            'support.inbound.mailgun.signing_key' => '',
            'queue.default' => 'database',
            'logging.channels.single.level' => 'warning',
        ]);

        $this->artisan('support:check-production')
            ->assertFailed();
    }
}
