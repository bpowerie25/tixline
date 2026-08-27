<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The 75%-elapsed SLA warning used to be derived at read time by comparing
     * wall-clock minutes. Under business hours that comparison needs the
     * tenant's schedule, which would mean two queries per ticket every time a
     * list is serialised. Storing the moment instead keeps `sla_status` free
     * of queries and lets the breach command find at-risk tickets in SQL.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('sla_warning_at')->nullable()->after('sla_resolution_due_at');
        });

        // Backfill at the old 75%-of-wall-clock definition so tickets that are
        // already open keep warning at the point they would have before.
        DB::table('tickets')
            ->whereNotNull('sla_resolution_due_at')
            ->orderBy('id')
            ->chunkById(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $created = Carbon\Carbon::parse($ticket->created_at);
                    $due = Carbon\Carbon::parse($ticket->sla_resolution_due_at);
                    $total = $created->diffInSeconds($due);

                    if ($total <= 0) {
                        continue;
                    }

                    DB::table('tickets')->where('id', $ticket->id)->update([
                        'sla_warning_at' => $created->copy()->addSeconds((int) round($total * 0.75)),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('sla_warning_at');
        });
    }
};
