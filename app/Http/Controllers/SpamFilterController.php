<?php

namespace App\Http\Controllers;

use App\Models\SpamFilterEntry;
use App\Services\ActivityLogger;
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

        $entry = SpamFilterEntry::firstOrCreate(
            ['type' => $validated['type'], 'value' => $validated['value']],
            ['reason' => $validated['reason'] ?? null]
        );

        ActivityLogger::log('spam_filter_created', "Added {$validated['type']} entry: {$validated['value']}", $entry);

        return back()->with('success', ucfirst($validated['type']) . ' entry added: ' . $validated['value']);
    }

    public function destroy(SpamFilterEntry $spamFilter)
    {
        ActivityLogger::log('spam_filter_deleted', "Removed {$spamFilter->type} entry: {$spamFilter->value}", $spamFilter);

        $spamFilter->delete();

        return back()->with('success', 'Entry removed.');
    }
}
