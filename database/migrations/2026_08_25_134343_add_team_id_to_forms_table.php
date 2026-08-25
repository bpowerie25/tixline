<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
