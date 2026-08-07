<?php

namespace App\Http\Controllers;

use App\Models\SpamFilterEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpamFilterController extends Controller
{
    public function index()
    {
        return Inertia::render('Settings/SpamFilters', [
            'entries' => SpamFilterEntry::latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:blocklist,allowlist',
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:255',
        ]);

        $validated['value'] = strtolower(trim($validated['value']));

        SpamFilterEntry::firstOrCreate(
            ['type' => $validated['type'], 'value' => $validated['value']],
            ['reason' => $validated['reason'] ?? null]
        );

        return back()->with('success', ucfirst($validated['type']) . ' entry added: ' . $validated['value']);
    }

    public function destroy(SpamFilterEntry $spamFilter)
    {
        $spamFilter->delete();

        return back()->with('success', 'Entry removed.');
    }
}
