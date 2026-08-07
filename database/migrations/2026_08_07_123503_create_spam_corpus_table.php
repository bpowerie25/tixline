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
        Schema::create('spam_corpus', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // keyword, phrase, email, domain
            $table->string('value');
            $table->unsignedInteger('hits')->default(1);
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spam_corpus');
    }
};
