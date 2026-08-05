<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            $table->json('auth_results')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('inbound_emails', function (Blueprint $table) {
            $table->dropColumn('auth_results');
        });
    }
};
