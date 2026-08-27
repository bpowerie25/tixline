<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::forTenant($this->currentTenantId())
            ->with('tokenable')
            ->latest()
            ->get()
            ->map(fn (ApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'abilities' => $key->abilities ?? [],
                'created_by' => $key->tokenable?->name,
                'created_at' => $key->created_at,
                'last_used_at' => $key->last_used_at,
                'expires_at' => $key->expires_at,
                'is_expired' => $key->expires_at !== null && $key->expires_at->isPast(),
            ]);

        return Inertia::render('Settings/ApiKeys', [
            'keys' => $keys,
            'abilities' => ApiKey::ABILITIES,
            // Present for exactly one render, immediately after creation: the
            // plaintext key is never recoverable afterwards.
            'plaintextKey' => session('plaintext_api_key'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_keys(ApiKey::ABILITIES))],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $expiresAt = isset($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $token = $request->user()->createToken(
            $validated['name'],
            $validated['abilities'],
            $expiresAt,
        );

        $token->accessToken->forceFill([
            'tenant_id' => $this->currentTenantId(),
        ])->save();

        return back()
            ->with('plaintext_api_key', $token->plainTextToken)
            ->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function destroy(ApiKey $apiKey)
    {
        // Scoped by hand rather than by the global scope: personal access
        // tokens are Sanctum's model, so nothing applies TenantScope to them.
        if ($apiKey->tenant_id !== $this->currentTenantId()) {
            abort(404);
        }

        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }

    protected function currentTenantId(): ?int
    {
        return app()->bound('tenant') ? app('tenant')->id : null;
    }
}
