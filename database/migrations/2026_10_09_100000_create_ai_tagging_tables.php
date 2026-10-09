<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tags — AI-managed taxonomy (separate from manual labels)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#6b7280');
            $table->text('description')->nullable();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['slug', 'tenant_id']);
        });

        // Pivot: tag <-> ticket (many-to-many)
        Schema::create('tag_ticket', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->boolean('is_ai_suggested')->default(true);
            $table->boolean('is_confirmed')->default(false);
            $table->primary(['tag_id', 'ticket_id']);
        });

        // AI tagging configuration per tenant
        Schema::create('ai_tagging_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('provider')->default('gemini'); // gemini, claude, openai, mistral
            $table->string('model')->nullable(); // e.g. gemini-2.0-flash, claude-sonnet-4-20250514, gpt-4o, mistral-large-latest
            $table->text('api_key')->nullable(); // encrypted
            $table->boolean('auto_apply')->default(false); // auto-apply or suggest only
            $table->decimal('confidence_threshold', 4, 3)->default(0.700);
            $table->boolean('flag_miscategorised')->default(true);
            $table->boolean('tag_on_create')->default(true); // real-time on ticket creation
            $table->timestamps();
            $table->unique('tenant_id');
        });

        // AI analysis results on tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('ai_suggested_tags')->nullable()->after('custom_fields');
            $table->boolean('ai_flagged')->default(false)->after('ai_suggested_tags');
            $table->text('ai_flag_reason')->nullable()->after('ai_flagged');
            $table->timestamp('ai_processed_at')->nullable()->after('ai_flag_reason');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ai_suggested_tags', 'ai_flagged', 'ai_flag_reason', 'ai_processed_at']);
        });

        Schema::dropIfExists('ai_tagging_configs');
        Schema::dropIfExists('tag_ticket');
        Schema::dropIfExists('tags');
    }
};
