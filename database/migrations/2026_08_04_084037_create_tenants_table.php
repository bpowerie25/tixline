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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();

            // Branding
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('primary_color', 7)->default('#6366f1');
            $table->string('secondary_color', 7)->default('#4f46e5');
            $table->string('accent_color', 7)->default('#818cf8');
            $table->string('header_bg_color', 7)->default('#ffffff');
            $table->string('header_text_color', 7)->default('#111827');
            $table->string('sidebar_bg_color', 7)->default('#f9fafb');
            $table->text('custom_css')->nullable();
            $table->string('font_family')->nullable();

            // Portal branding
            $table->string('portal_title')->nullable();
            $table->text('portal_welcome_text')->nullable();
            $table->string('support_email')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('form_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::dropIfExists('tenants');
    }
};
