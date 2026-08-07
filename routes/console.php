<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('support:run-automations')->everyFiveMinutes();
Schedule::command('support:check-sla')->everyFiveMinutes();
Schedule::command('support:purge-payloads')->daily();
Schedule::command('support:poll-imap')->everyFiveMinutes();
