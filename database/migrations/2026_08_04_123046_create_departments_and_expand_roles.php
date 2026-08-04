<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments (groups)
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Link teams to departments
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('color')->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
        });

        // Expand user roles — SQLite can't alter enums, so we add a new column and drop old
        Schema::table('users', function (Blueprint $table) {
            $table->string('role_name', 20)->default('agent')->after('role');
        });

        // Copy existing role values
        DB::table('users')->update(['role_name' => DB::raw('role')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('role_name', 'role');
        });

        // Permissions table for granular control
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->string('role', 20);
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['lead_id']);
            $table->dropColumn(['department_id', 'lead_id']);
        });

        Schema::dropIfExists('departments');
    }
};
