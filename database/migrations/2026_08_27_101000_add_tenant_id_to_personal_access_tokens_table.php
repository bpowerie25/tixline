<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalised from the owning user so a tenant's API keys can be listed
     * and revoked without joining through tokenable_type/tokenable_id, and so
     * a key stays attributable after the agent who created it is deleted.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
        });

        DB::statement(
            'UPDATE personal_access_tokens SET tenant_id = ('.
            'SELECT tenant_id FROM users WHERE users.id = personal_access_tokens.tokenable_id'.
            ') WHERE tokenable_type = ?',
            [User::class]
        );
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
