<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('duplicate_of')->nullable()->after('resolved_at');
            $table->foreign('duplicate_of')->references('id')->on('tickets')->nullOnDelete();
            $table->index('duplicate_of');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['duplicate_of']);
            $table->dropColumn('duplicate_of');
        });
    }
};
