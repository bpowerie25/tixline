<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
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
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', $this->uniqueName()],
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
        if (! $role->isEditableByTenant()) {
            // The system roles are a single shared row each. Editing one here
            // rewrote permissions for every tenant on the platform: granting
            // the agent role everything handed an unrelated customer's agents
            // roles.manage along with it.
            return back()->with('error',
                'The built-in roles are shared across the platform and cannot be changed. '
                .'Create a custom role instead.');
        }

        $rules = [
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];

        // Allow slug change only for non-system roles
        if (! $role->is_system) {
            $rules['name'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', $this->uniqueName($role->id)];
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

    /**
     * Unique within the tenant rather than globally: two tenants may each
     * want a role called "supervisor" and neither should block the other.
     */
    protected function uniqueName(?int $ignoreId = null): Unique
    {
        $rule = Rule::unique('roles', 'name')
            ->where('tenant_id', app()->bound('tenant') ? app('tenant')->id : null);

        return $ignoreId ? $rule->ignore($ignoreId) : $rule;
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
