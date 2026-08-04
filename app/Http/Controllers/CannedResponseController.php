<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CannedResponseController extends Controller
{
    public function index(Request $request)
    {
        $responses = CannedResponse::with('user:id,name')
            ->where(function ($q) use ($request) {
                $q->where('is_shared', true)
                  ->orWhere('user_id', $request->user()->id);
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('CannedResponses/Index', [
            'cannedResponses' => $responses,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shortcode' => 'required|string|max:50|unique:canned_responses',
            'body' => 'required|string',
            'is_shared' => 'boolean',
        ]);

        $validated['user_id'] = $request->user()->id;

        CannedResponse::create($validated);

        return back()->with('success', 'Canned response created.');
    }

    public function update(Request $request, CannedResponse $cannedResponse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shortcode' => 'required|string|max:50|unique:canned_responses,shortcode,' . $cannedResponse->id,
            'body' => 'required|string',
            'is_shared' => 'boolean',
        ]);

        $cannedResponse->update($validated);

        return back()->with('success', 'Canned response updated.');
    }

    public function destroy(CannedResponse $cannedResponse)
    {
        $cannedResponse->delete();

        return back()->with('success', 'Canned response deleted.');
    }

    public function forTicket(Request $request)
    {
        return CannedResponse::where(function ($q) use ($request) {
            $q->where('is_shared', true)
              ->orWhere('user_id', $request->user()->id);
        })->orderBy('name')->get(['id', 'name', 'shortcode', 'body']);
    }
}
