<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers are tenant-scoped, so their email addresses cannot be globally
 * unique: the same person is entitled to be a customer of more than one
 * company on the platform. Under the global constraint, the second tenant to
 * receive mail from an address already known to another tenant fails outright
 * when the portal account is auto-created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_email_unique');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
        });
    }
};
