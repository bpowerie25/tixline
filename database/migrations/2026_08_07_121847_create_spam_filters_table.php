<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spam_filters', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // blocklist, allowlist
            $table->string('value'); // email or domain
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_filters');
    }
};
