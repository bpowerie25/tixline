<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('allow_admin_view')->default(false)->after('is_restricted');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('auto_assign_solo')->default(false)->after('agent_anonymous_label');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_manage_reports')->default(false)->after('is_external');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('allow_admin_view');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('auto_assign_solo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_manage_reports');
        });
    }
};
