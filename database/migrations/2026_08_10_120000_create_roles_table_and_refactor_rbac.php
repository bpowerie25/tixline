<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create roles table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // 2. Seed default roles
        $now = now();
        $roles = [
            ['name' => 'admin', 'display_name' => 'Admin', 'description' => 'Full system access', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'group_manager', 'display_name' => 'Group Manager', 'description' => 'Manages department groups', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'team_lead', 'display_name' => 'Team Lead', 'description' => 'Leads a team', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'agent', 'display_name' => 'Agent', 'description' => 'Support agent', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('roles')->insert($roles);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $gmRoleId = DB::table('roles')->where('name', 'group_manager')->value('id');
        $tlRoleId = DB::table('roles')->where('name', 'team_lead')->value('id');
        $agentRoleId = DB::table('roles')->where('name', 'agent')->value('id');

        // 3. Drop existing permission_role pivot (uses string role column)
        Schema::dropIfExists('permission_role');

        // 4. Handle permissions table
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->truncate();

            // Ensure the table has the right columns
            if (! Schema::hasColumn('permissions', 'group')) {
                Schema::table('permissions', function (Blueprint $table) {
                    $table->string('group')->after('display_name')->default('');
                });
            }
            if (! Schema::hasColumn('permissions', 'display_name')) {
                Schema::table('permissions', function (Blueprint $table) {
                    $table->string('display_name')->after('name')->default('');
                });
            }
        } else {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name');
                $table->string('group');
                $table->timestamps();
            });
        }

        // 5. Recreate permission_role with FK to roles table
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // 6. Seed all permissions
        $permissions = [
            ['group' => 'tickets', 'name' => 'tickets.view', 'display_name' => 'View Tickets'],
            ['group' => 'tickets', 'name' => 'tickets.create', 'display_name' => 'Create Tickets'],
            ['group' => 'tickets', 'name' => 'tickets.update', 'display_name' => 'Update Tickets'],
            ['group' => 'tickets', 'name' => 'tickets.delete', 'display_name' => 'Delete Tickets'],
            ['group' => 'tickets', 'name' => 'tickets.assign', 'display_name' => 'Assign Tickets'],
            ['group' => 'tickets', 'name' => 'tickets.bulk', 'display_name' => 'Bulk Actions'],
            ['group' => 'comments', 'name' => 'comments.create', 'display_name' => 'Add Comments'],
            ['group' => 'reports', 'name' => 'reports.view', 'display_name' => 'View Reports'],
            ['group' => 'reports', 'name' => 'reports.custom.manage', 'display_name' => 'Manage Custom Reports'],
            ['group' => 'reports', 'name' => 'reports.custom.export', 'display_name' => 'Export Reports'],
            ['group' => 'kb', 'name' => 'kb.admin.view', 'display_name' => 'View KB Admin'],
            ['group' => 'kb', 'name' => 'kb.admin.manage', 'display_name' => 'Manage KB Articles'],
            ['group' => 'teams', 'name' => 'teams.view', 'display_name' => 'View Teams'],
            ['group' => 'teams', 'name' => 'teams.manage', 'display_name' => 'Manage Teams'],
            ['group' => 'agents', 'name' => 'agents.view', 'display_name' => 'View Agents'],
            ['group' => 'agents', 'name' => 'agents.manage', 'display_name' => 'Manage Agents'],
            ['group' => 'labels', 'name' => 'labels.view', 'display_name' => 'View Labels'],
            ['group' => 'labels', 'name' => 'labels.manage', 'display_name' => 'Manage Labels'],
            ['group' => 'workflows', 'name' => 'workflows.view', 'display_name' => 'View Workflows'],
            ['group' => 'workflows', 'name' => 'workflows.manage', 'display_name' => 'Manage Workflows'],
            ['group' => 'forms', 'name' => 'forms.view', 'display_name' => 'View Forms'],
            ['group' => 'forms', 'name' => 'forms.manage', 'display_name' => 'Manage Forms'],
            ['group' => 'canned', 'name' => 'canned-responses.view', 'display_name' => 'View Canned Responses'],
            ['group' => 'canned', 'name' => 'canned-responses.manage', 'display_name' => 'Manage Canned Responses'],
            ['group' => 'sla', 'name' => 'sla-policies.view', 'display_name' => 'View SLA Policies'],
            ['group' => 'sla', 'name' => 'sla-policies.manage', 'display_name' => 'Manage SLA Policies'],
            ['group' => 'tenants', 'name' => 'tenants.view', 'display_name' => 'View Tenants'],
            ['group' => 'tenants', 'name' => 'tenants.manage', 'display_name' => 'Manage Tenants'],
            ['group' => 'mail', 'name' => 'mail.view', 'display_name' => 'View Mail Config'],
            ['group' => 'mail', 'name' => 'mail.manage', 'display_name' => 'Manage Mail Config'],
            ['group' => 'mail', 'name' => 'inbound-emails.view', 'display_name' => 'View Inbound Emails'],
            ['group' => 'mail', 'name' => 'inbound-emails.manage', 'display_name' => 'Manage Inbound Emails'],
            ['group' => 'spam', 'name' => 'spam-filters.view', 'display_name' => 'View Spam Filters'],
            ['group' => 'spam', 'name' => 'spam-filters.manage', 'display_name' => 'Manage Spam Filters'],
            ['group' => 'roles', 'name' => 'roles.view', 'display_name' => 'View Roles'],
            ['group' => 'roles', 'name' => 'roles.manage', 'display_name' => 'Manage Roles'],
            ['group' => 'departments', 'name' => 'departments.view', 'display_name' => 'View Departments'],
            ['group' => 'departments', 'name' => 'departments.manage', 'display_name' => 'Manage Departments'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->insert(array_merge($perm, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // 7. Get all permission IDs for role assignment
        $allPermIds = DB::table('permissions')->pluck('id', 'name');

        // Admin: ALL permissions
        $adminPerms = $allPermIds->values()->toArray();

        // Group Manager: tickets.*, comments.*, reports.*, kb.admin.*, canned-responses.*, departments.view
        $gmPerms = $allPermIds->filter(function ($id, $name) {
            return str_starts_with($name, 'tickets.')
                || str_starts_with($name, 'comments.')
                || str_starts_with($name, 'reports.')
                || str_starts_with($name, 'kb.admin.')
                || str_starts_with($name, 'canned-responses.')
                || $name === 'departments.view';
        })->values()->toArray();

        // Team Lead: tickets.*, comments.*, reports.*, kb.admin.*, canned-responses.*
        $tlPerms = $allPermIds->filter(function ($id, $name) {
            return str_starts_with($name, 'tickets.')
                || str_starts_with($name, 'comments.')
                || str_starts_with($name, 'reports.')
                || str_starts_with($name, 'kb.admin.')
                || str_starts_with($name, 'canned-responses.');
        })->values()->toArray();

        // Agent: tickets.view, tickets.create, tickets.update, comments.create, canned-responses.view, canned-responses.manage
        $agentPerms = $allPermIds->filter(function ($id, $name) {
            return in_array($name, [
                'tickets.view',
                'tickets.create',
                'tickets.update',
                'comments.create',
                'canned-responses.view',
                'canned-responses.manage',
            ]);
        })->values()->toArray();

        // Insert role-permission mappings
        $pivotRows = [];
        foreach ($adminPerms as $permId) {
            $pivotRows[] = ['role_id' => $adminRoleId, 'permission_id' => $permId];
        }
        foreach ($gmPerms as $permId) {
            $pivotRows[] = ['role_id' => $gmRoleId, 'permission_id' => $permId];
        }
        foreach ($tlPerms as $permId) {
            $pivotRows[] = ['role_id' => $tlRoleId, 'permission_id' => $permId];
        }
        foreach ($agentPerms as $permId) {
            $pivotRows[] = ['role_id' => $agentRoleId, 'permission_id' => $permId];
        }

        DB::table('permission_role')->insert($pivotRows);

        // 8. Add role_id to users and migrate existing data
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('id');
        });

        // Map existing role strings to role IDs
        DB::table('users')->where('role', 'admin')->update(['role_id' => $adminRoleId]);
        DB::table('users')->where('role', 'group_manager')->update(['role_id' => $gmRoleId]);
        DB::table('users')->where('role', 'team_lead')->update(['role_id' => $tlRoleId]);
        DB::table('users')->where('role', 'agent')->update(['role_id' => $agentRoleId]);
        // Any remaining users default to agent
        DB::table('users')->whereNull('role_id')->update(['role_id' => $agentRoleId]);

        // Make role_id NOT NULL with default and add FK
        Schema::table('users', function (Blueprint $table) use ($agentRoleId) {
            $table->unsignedBigInteger('role_id')->default($agentRoleId)->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles');
        });

        // Drop the old role string column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        // Re-add role string column
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('agent')->after('id');
        });

        // Map role IDs back to strings
        $roles = DB::table('roles')->pluck('name', 'id');
        foreach ($roles as $id => $name) {
            DB::table('users')->where('role_id', $id)->update(['role' => $name]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');

        // Don't drop permissions table — it existed before this migration
        // but truncate what we seeded
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->truncate();
        }
    }
};
