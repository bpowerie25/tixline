<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agentRoleId = DB::table('roles')->where('name', 'agent')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'tickets.assign')->value('id');

        if ($agentRoleId && $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $agentRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $agentRoleId = DB::table('roles')->where('name', 'agent')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'tickets.assign')->value('id');

        if ($agentRoleId && $permissionId) {
            DB::table('permission_role')
                ->where('role_id', $agentRoleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
