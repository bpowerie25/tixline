<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultTenantId = Tenant::first()?->id;

        if ($defaultTenantId) {
            DB::table('customers')
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        // Not reversible — we don't know which were originally null
    }
};
