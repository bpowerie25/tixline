<?php

namespace App\Contracts;

interface PlanGate
{
    /**
     * Check if a feature is available for the given tenant.
     */
    public function check(string $feature, ?int $tenantId = null): bool;

    /**
     * Check if a countable resource can be created for the given tenant.
     */
    public function canCreate(string $resource, ?int $tenantId = null): bool;
}
