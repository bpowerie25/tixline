<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'tenant_id']);
        });

        Schema::create('custom_report_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_report_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type');
            $table->string('chart_type')->default('bar');
            $table->string('title');
            $table->unsignedSmallInteger('grid_x')->default(0);
            $table->unsignedSmallInteger('grid_y')->default(0);
            $table->unsignedSmallInteger('grid_w')->default(6);
            $table->unsignedSmallInteger('grid_h')->default(4);
            $table->json('filters')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index('custom_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_report_widgets');
        Schema::dropIfExists('custom_reports');
    }
};
