<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DepartmentController extends Controller
{
    public function index()
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::with('manager:id,name')
                ->withCount('teams')
                ->get(),
            'users' => User::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        Department::create($validated);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $department->update($validated);

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
