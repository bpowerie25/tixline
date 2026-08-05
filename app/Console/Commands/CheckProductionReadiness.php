<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckProductionReadiness extends Command
{
    protected $signature = 'support:check-production';

    protected $description = 'Verify environment settings are safe for production deployment';

    public function handle(): int
    {
        $failures = 0;

        $checks = [
            ['APP_DEBUG must be false', fn () => config('app.debug') === false],
            ['APP_ENV must be production', fn () => config('app.env') === 'production'],
            ['SESSION_SECURE_COOKIE must be true', fn () => config('session.secure') === true],
            ['LOG_LEVEL should be warning or higher', fn () => in_array(
                config('logging.channels.single.level', config('logging.level', 'debug')),
                ['warning', 'error', 'critical', 'alert', 'emergency'],
            )],
            ['MAIL_MAILER must not be log (writes personal data)', fn () => config('mail.default') !== 'log'],
            ['INBOUND_WEBHOOK_SECRET must be set', fn () => ! empty(config('support.inbound.webhook_secret'))],
            ['APP_URL must not be localhost', fn () => ! str_contains(config('app.url', 'http://localhost'), 'localhost')],
            ['APP_KEY must be set', fn () => ! empty(config('app.key'))],
            ['QUEUE_CONNECTION should not be sync', fn () => config('queue.default') !== 'sync'],
        ];

        foreach ($checks as [$label, $check]) {
            if ($check()) {
                $this->components->info("PASS: {$label}");
            } else {
                $this->components->error("FAIL: {$label}");
                $failures++;
            }
        }

        $this->newLine();

        if ($failures === 0) {
            $this->components->info('All production readiness checks passed.');

            return self::SUCCESS;
        }

        $this->components->error("{$failures} check(s) failed. Fix before deploying to production.");

        return self::FAILURE;
    }
}
