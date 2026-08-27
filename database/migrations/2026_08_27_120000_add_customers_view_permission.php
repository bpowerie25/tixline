<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            'name' => 'customers.view',
            'display_name' => 'View Requesters',
            'group' => 'customers',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permId = DB::table('permissions')->where('name', 'customers.view')->value('id');

        // Grant to Admin and Group Manager roles
        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'group_manager', 'team_lead'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insert([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'customers.view')->value('id');

        if ($permId) {
            DB::table('permission_role')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
