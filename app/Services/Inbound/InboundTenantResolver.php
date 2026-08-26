<?php

namespace App\Services\Inbound;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;

/**
 * Works out which tenant an inbound message was addressed to.
 *
 * Tenants receive mail at {slug}@{inbound_domain}, a domain the platform
 * controls, and only that domain is authoritative. Matching on addresses the
 * tenant supplied themselves -- support_email, say -- is deliberately not done:
 * those are unverified, so one tenant could claim another's address and have
 * their mail delivered into it.
 *
 * When nothing matches, resolution fails rather than falling back to a default
 * tenant. Misrouting a customer's email into another company's helpdesk is far
 * worse than parking it for review.
 */
class InboundTenantResolver
{
    /**
     * Resolve from any number of candidate recipient addresses, in priority
     * order. Returns null when none of them name a known tenant.
     */
    public function resolve(array $candidates): ?Tenant
    {
        foreach ($candidates as $candidate) {
            foreach ($this->addressesIn((string) $candidate) as $address) {
                $slug = $this->slugFor($address);

                if ($slug === null) {
                    continue;
                }

                $tenant = Tenant::withoutGlobalScope(TenantScope::class)
                    ->where('slug', $slug)
                    ->where('is_active', true)
                    ->first();

                if ($tenant) {
                    return $tenant;
                }
            }
        }

        return null;
    }

    /**
     * The address a tenant receives mail at.
     */
    public function addressFor(Tenant $tenant): ?string
    {
        $domain = $this->domain();

        return $domain ? "{$tenant->slug}@{$domain}" : null;
    }

    /**
     * The tenant slug an address routes to, or null if it is not ours.
     */
    protected function slugFor(string $address): ?string
    {
        $domain = $this->domain();

        if (! $domain || ! str_contains($address, '@')) {
            return null;
        }

        [$localPart, $addressDomain] = explode('@', $address, 2);

        if (strcasecmp(trim($addressDomain), $domain) !== 0) {
            return null;
        }

        // acme+billing@ and acme@ are the same tenant.
        $localPart = strtolower(trim($localPart));
        $localPart = explode('+', $localPart, 2)[0];

        return $localPart !== '' ? $localPart : null;
    }

    /**
     * Pull every address out of a header value, which may hold a display name,
     * angle brackets, or a comma-separated list.
     */
    protected function addressesIn(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        preg_match_all('/[^\s<>,;"]+@[^\s<>,;"]+/', $value, $matches);

        return array_map(
            fn (string $address) => strtolower(rtrim(trim($address), '.>')),
            $matches[0] ?? [],
        );
    }

    protected function domain(): ?string
    {
        $domain = config('support.inbound.domain');

        return $domain ? strtolower(ltrim(trim($domain), '@')) : null;
    }
}
