<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LabelController extends Controller
{
    public function index()
    {
        return Inertia::render('Labels/Index', [
            'labels' => Label::withCount('tickets')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $label = Label::create($validated);

        ActivityLogger::log('label_created', "Created label {$label->name}", $label);

        return back()->with('success', 'Label created.');
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $label->update($validated);

        ActivityLogger::log('label_updated', "Updated label {$label->name}", $label);

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Label $label)
    {
        ActivityLogger::log('label_deleted', "Deleted label {$label->name}", $label);

        $label->delete();

        return back()->with('success', 'Label deleted.');
    }
}
