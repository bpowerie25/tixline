<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles were global. Every tenant shared one set of rows, and the screen that
 * edits them is reachable by any tenant admin, so one customer could rewrite
 * the permissions of the `agent` role and hand every agent at every other
 * tenant whatever they liked -- verified before this migration: granting the
 * agent role all permissions gave an unrelated tenant's agent roles.manage.
 *
 * System roles stay global and shared, because they are the product's own
 * vocabulary and every tenant needs them. What changes is that they become
 * read-only under MULTI_TENANT, and that a custom role now belongs to the
 * tenant that made it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        // A custom role that already exists belongs to whichever tenant its
        // users are in. System roles keep a null tenant_id, which is what
        // makes them shared.
        foreach (DB::table('roles')->where('is_system', false)->get() as $role) {
            $tenantId = DB::table('users')
                ->where('role_id', $role->id)
                ->whereNotNull('tenant_id')
                ->value('tenant_id');

            if ($tenantId) {
                DB::table('roles')->where('id', $role->id)->update(['tenant_id' => $tenantId]);
            }
        }

        // Two tenants may each want a role called "supervisor", so the slug
        // can only be unique within a tenant. SQLite cannot drop a unique
        // index by column list, so both paths are spelled out.
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_unique');
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->unique('name');
        });
    }
};
