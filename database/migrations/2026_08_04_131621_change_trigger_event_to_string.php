<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER COLUMN, so recreate
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('trigger_event_new')->default('ticket_created')->after('trigger_event');
        });

        DB::table('workflows')->update(['trigger_event_new' => DB::raw('trigger_event')]);

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('trigger_event');
        });

        Schema::table('workflows', function (Blueprint $table) {
            $table->renameColumn('trigger_event_new', 'trigger_event');
        });
    }

    public function down(): void
    {
        // No rollback needed
    }
};
