<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('sla_warning_fired')->default(false);
            $table->boolean('sla_response_breach_fired')->default(false);
            $table->boolean('sla_resolution_breach_fired')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_warning_fired', 'sla_response_breach_fired', 'sla_resolution_breach_fired']);
        });
    }
};
