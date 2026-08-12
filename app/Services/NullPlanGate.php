<?php

namespace App\Services;

use App\Contracts\PlanGate;

class NullPlanGate implements PlanGate
{
    public function check(string $feature, ?int $tenantId = null): bool
    {
        return true;
    }

    public function canCreate(string $resource, ?int $tenantId = null): bool
    {
        return true;
    }
}
