<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->string('name')->default('Business Hours');
            $table->string('timezone')->default('UTC');

            // Weekly schedule: { "1": [{"start": "09:00", "end": "17:00"}], ... }
            // keyed by ISO weekday (1 = Monday ... 7 = Sunday). A missing or
            // empty key means the business is closed that day.
            $table->json('days');

            // [{"date": "2026-12-25", "name": "Christmas Day"}]
            $table->json('holidays')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
    }
};
