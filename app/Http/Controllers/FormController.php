<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FormController extends Controller
{
    public function index()
    {
        return Inertia::render('Forms/Index', [
            'forms' => Form::withCount('fields', 'tickets')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Forms/Show', [
            'form' => null,
            'teams' => Team::all(['id', 'name']),
        ]);
    }

    public function show(Form $form)
    {
        $form->load('fields');

        return Inertia::render('Forms/Show', [
            'form' => $form,
            'teams' => Team::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'team_id' => 'nullable|exists:teams,id',
            'fields' => 'nullable|array',
            'fields.*.name' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.description' => 'nullable|string|max:500',
            'fields.*.type' => 'required|in:text,textarea,select,checkbox,radio,email,number,date,file',
            'fields.*.options' => 'nullable|array',
            'fields.*.is_required' => 'boolean',
            'fields.*.sort_order' => 'integer',
            'fields.*.conditions' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $fields = $validated['fields'] ?? [];
        unset($validated['fields']);

        $form = Form::create($validated);

        foreach ($fields as $field) {
            $form->fields()->create($field);
        }

        return back()->with('success', 'Form created.');
    }

    public function update(Request $request, Form $form)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'team_id' => 'nullable|exists:teams,id',
            'fields' => 'nullable|array',
            'fields.*.id' => 'nullable|integer',
            'fields.*.name' => 'required|string',
            'fields.*.label' => 'required|string',
            'fields.*.description' => 'nullable|string|max:500',
            'fields.*.type' => 'required|in:text,textarea,select,checkbox,radio,email,number,date,file',
            'fields.*.options' => 'nullable|array',
            'fields.*.is_required' => 'boolean',
            'fields.*.sort_order' => 'integer',
            'fields.*.conditions' => 'nullable|array',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $fields = $validated['fields'] ?? [];
        unset($validated['fields']);

        $form->update($validated);

        $existingIds = collect($fields)->pluck('id')->filter()->all();
        $form->fields()->whereNotIn('id', $existingIds)->delete();

        foreach ($fields as $fieldData) {
            if (! empty($fieldData['id'])) {
                $form->fields()->where('id', $fieldData['id'])->update($fieldData);
            } else {
                unset($fieldData['id']);
                $form->fields()->create($fieldData);
            }
        }

        return back()->with('success', 'Form updated.');
    }

    public function destroy(Form $form)
    {
        $form->delete();

        return back()->with('success', 'Form deleted.');
    }
}
