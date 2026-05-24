<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next, string $scope = 'manage'): Response
    {
        $tenant = $request->route('tenant');
        $user = $request->user();

        if (! $tenant instanceof Tenant || ! $user) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $allowed = $scope === 'work'
            ? $user->belongsToTenant($tenant)
            : $user->managesTenant($tenant);

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
