<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            'name' => 'ai-tagging.manage',
            'display_name' => 'Manage AI Tagging',
            'group' => 'settings',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permId = DB::table('permissions')->where('name', 'ai-tagging.manage')->value('id');

        // Grant to Admin role only
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($adminRoleId && $permId) {
            DB::table('permission_role')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permId,
            ]);
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'ai-tagging.manage')->value('id');

        if ($permId) {
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
