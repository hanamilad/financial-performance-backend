<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon dashboard access (FOUNDATION-005).
 *
 * Horizon's own check is `Gate::check('viewHorizon') || environment('local')`,
 * so leaving this gate permanently closed gives exactly the approved rule: the
 * dashboard is reachable on a local machine and returns 403 in every other
 * environment. No Basic Auth, no custom middleware and no environment secret is
 * involved — this is Horizon's built-in mechanism.
 *
 * The gate cannot allow anyone yet because the application has no
 * authentication at all: Sanctum, sessions and roles are deferred to the
 * authentication slice (DEC-035). This gate is reopened there and bound to
 * SYSTEM_ADMIN.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?Authenticatable $user = null): bool => false);
    }
}
