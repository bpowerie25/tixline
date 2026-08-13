<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'workflows',
            'labels',
            'teams',
            'departments',
            'forms',
            'canned_responses',
            'sla_policies',
            'kb_categories',
            'kb_articles',
            'customers',
            'mail_configurations',
            'spam_filters',
            'automations',
            'activity_logs',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable();
            });
        }

        // Add foreign key constraints separately to avoid SQLite table rebuild issues
        if (DB::getDriverName() !== 'sqlite') {
            foreach ($tables as $table) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'workflows',
            'labels',
            'teams',
            'departments',
            'forms',
            'canned_responses',
            'sla_policies',
            'kb_categories',
            'kb_articles',
            'customers',
            'mail_configurations',
            'spam_filters',
            'automations',
            'activity_logs',
        ];

        if (DB::getDriverName() !== 'sqlite') {
            foreach ($tables as $table) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropForeign(['tenant_id']);
                });
            }
        }

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
