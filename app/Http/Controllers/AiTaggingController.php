<?php

namespace App\Http\Controllers;

use App\Models\AiTaggingConfig;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AiTaggingController extends Controller
{
    public function index()
    {
        $config = AiTaggingConfig::first();

        return Inertia::render('Settings/AiTagging', [
            'config' => $config ? [
                'id' => $config->id,
                'is_enabled' => $config->is_enabled,
                'provider' => $config->provider,
                'model' => $config->model,
                'has_api_key' => ! empty($config->api_key),
                'auto_apply' => $config->auto_apply,
                'confidence_threshold' => (float) $config->confidence_threshold,
                'flag_miscategorised' => $config->flag_miscategorised,
                'tag_on_create' => $config->tag_on_create,
            ] : null,
            'providers' => AiTaggingConfig::providerOptions(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function storeConfig(Request $request)
    {
        $validated = $request->validate([
            'is_enabled' => 'boolean',
            'provider' => 'required|in:gemini,claude,openai,mistral',
            'model' => 'required|string|max:100',
            'api_key' => 'nullable|string',
            'auto_apply' => 'boolean',
            'confidence_threshold' => 'required|numeric|min:0|max:1',
            'flag_miscategorised' => 'boolean',
            'tag_on_create' => 'boolean',
        ]);

        $config = AiTaggingConfig::first();

        if ($config) {
            if (empty($validated['api_key'])) {
                unset($validated['api_key']);
            }
            $config->update($validated);
        } else {
            if (empty($validated['api_key'])) {
                return back()->withErrors(['api_key' => 'API key is required for initial setup.']);
            }
            AiTaggingConfig::create($validated);
        }

        return back()->with('success', 'AI tagging configuration saved.');
    }

    public function storeTag(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if (Tag::where('slug', $validated['slug'])->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.']);
        }

        Tag::create($validated);

        return back()->with('success', 'Tag created.');
    }

    public function updateTag(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:500',
        ]);

        $newSlug = Str::slug($validated['name']);

        if ($newSlug !== $tag->slug && Tag::where('slug', $newSlug)->exists()) {
            return back()->withErrors(['name' => 'A tag with this name already exists.']);
        }

        $validated['slug'] = $newSlug;
        $tag->update($validated);

        return back()->with('success', 'Tag updated.');
    }

    public function destroyTag(Tag $tag)
    {
        $tag->delete();

        return back()->with('success', 'Tag deleted.');
    }

    public function dismissFlag(Request $request, \App\Models\Ticket $ticket)
    {
        $ticket->update([
            'ai_flagged' => false,
            'ai_flag_reason' => null,
        ]);

        return back()->with('success', 'Flag dismissed.');
    }

    public function confirmTag(Request $request, \App\Models\Ticket $ticket, Tag $tag)
    {
        $ticket->tags()->updateExistingPivot($tag->id, ['is_confirmed' => true]);

        return back()->with('success', 'Tag confirmed.');
    }

    public function removeTag(Request $request, \App\Models\Ticket $ticket, Tag $tag)
    {
        $ticket->tags()->detach($tag->id);

        return back()->with('success', 'Tag removed.');
    }
}
