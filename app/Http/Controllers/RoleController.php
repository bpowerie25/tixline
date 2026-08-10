<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RoleController extends Controller
{
    public function index()
    {
        return Inertia::render('Roles/Index', [
            'roles' => Role::with('permissions')
                ->withCount('users')
                ->get(),
            'permissions' => Permission::all()
                ->groupBy('group')
                ->map(fn ($perms) => $perms->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name,
                ])),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name|regex:/^[a-z0-9_]+$/',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        $role->permissions()->sync($validated['permissions'] ?? []);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $rules = [
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];

        // Allow slug change only for non-system roles
        if (! $role->is_system) {
            $rules['name'] = 'required|string|max:255|unique:roles,name,' . $role->id . '|regex:/^[a-z0-9_]+$/';
        }

        $validated = $request->validate($rules);

        $updateData = [
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ];

        if (! $role->is_system && isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }

        $role->update($updateData);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        // Reassign users to the agent role before deletion
        $agentRole = Role::where('name', Role::AGENT)->first();

        if ($agentRole) {
            $role->users()->update(['role_id' => $agentRole->id]);
        }

        $role->delete();

        return back()->with('success', 'Role deleted. Users reassigned to Agent role.');
    }
}
