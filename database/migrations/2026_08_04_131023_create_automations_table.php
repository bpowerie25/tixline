<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('time_conditions');
            $table->json('ticket_conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->boolean('run_once_per_ticket')->default(true);
            $table->timestamps();
        });

        // Track which automations have already fired on which tickets
        Schema::create('automation_ticket', function (Blueprint $table) {
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->timestamp('fired_at');
            $table->primary(['automation_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_ticket');
        Schema::dropIfExists('automations');
    }
};
