<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::SystemAdmin) {
            abort(403, 'This action is restricted to system administrators.');
        }

        return $next($request);
    }
}
