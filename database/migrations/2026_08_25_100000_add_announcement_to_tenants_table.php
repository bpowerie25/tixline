<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('announcement_enabled')->default(false)->after('portal_welcome_text');
            $table->text('announcement_text')->nullable()->after('announcement_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['announcement_enabled', 'announcement_text']);
        });
    }
};
