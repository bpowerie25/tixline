<?php

namespace App\Http\Middleware;

use App\Contracts\PlanGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a plan feature.
 *
 * The core binds NullPlanGate, so every feature is available in a self-hosted
 * install and this is a pass-through. It exists so core routes can carry
 * `plan:` middleware at all — the cloud app shadows this class with its own
 * copy, which redirects to billing instead of aborting.
 */
class RequirePlan
{
    public function __construct(
        protected PlanGate $planGate,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->planGate->check($feature)) {
            abort(403, 'This feature is not available on your plan.');
        }

        return $next($request);
    }
}
