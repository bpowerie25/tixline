<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            // Defaults to true so a tenant that configures business hours gets
            // them applied everywhere. Until a schedule exists the calculator
            // falls back to round-the-clock, so this is a no-op for existing
            // installs rather than a silent change to their SLA targets.
            $table->boolean('use_business_hours')->default(true)->after('resolution_hours');
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('use_business_hours');
        });
    }
};
