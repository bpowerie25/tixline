<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('anonymize_agents')->default(false)->after('reply_email_mode');
            $table->string('agent_anonymous_label')->nullable()->after('anonymize_agents');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['anonymize_agents', 'agent_anonymous_label']);
        });
    }
};
