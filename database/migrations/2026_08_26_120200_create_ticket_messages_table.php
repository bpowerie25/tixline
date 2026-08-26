<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps RFC 5322 Message-IDs to the ticket they belong to, in both directions.
 *
 * Threading previously relied on the [TKT-n] reference in the subject line.
 * Mail clients let people edit subjects, and many localised auto-forwards
 * rewrite them, so a reply whose subject has been touched starts a brand new
 * ticket. In-Reply-To and References survive that, but only if we remember the
 * IDs of the messages we sent and received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('message_id');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->timestamps();

            // Message-IDs are globally unique in principle, but two tenants can
            // legitimately receive the same message, so scope it as elsewhere.
            $table->unique(['tenant_id', 'message_id']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
