<?php

namespace App\Providers;

use App\Contracts\PlanGate;
use App\Listeners\AddMailLoopPreventionHeaders;
use App\Listeners\RecordOutboundMessageId;
use App\Services\NullPlanGate;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PlanGate::class, NullPlanGate::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Event::listen(MessageSending::class, AddMailLoopPreventionHeaders::class);
        Event::listen(MessageSending::class, RecordOutboundMessageId::class);
    }
}
