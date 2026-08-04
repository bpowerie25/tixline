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
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent']);
            $table->integer('first_response_hours');
            $table->integer('resolution_hours');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('priority');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_response_due_at')->nullable()->after('resolved_at');
            $table->timestamp('sla_resolution_due_at')->nullable()->after('sla_response_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_response_due_at', 'sla_resolution_due_at']);
        });
        Schema::dropIfExists('sla_policies');
    }
};
