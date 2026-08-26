<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound mail was the one part of the pipeline with no tenant of its own.
 *
 * Two consequences: the review UI listed every tenant's raw payloads to every
 * tenant's admins, and a queued job had no way to recover the tenant the
 * message was addressed to, so it processed with no tenant bound at all.
 *
 * Message-ID uniqueness also moves to being per-tenant. A message addressed to
 * two tenants is two separate deliveries, and under a global constraint the
 * second one is silently swallowed as a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('tenant_id');
        });

        // Single-tenant installs: everything already belongs to that tenant.
        $tenants = Tenant::withoutGlobalScopes()->limit(2)->pluck('id');

        if ($tenants->count() === 1) {
            DB::table('inbound_emails')->whereNull('tenant_id')->update(['tenant_id' => $tenants->first()]);
        }

        Schema::table('inbound_emails', function (Blueprint $table) {
            $table->dropUnique('inbound_emails_message_id_unique');
            $table->unique(['tenant_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'message_id']);
            $table->unique('message_id');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
