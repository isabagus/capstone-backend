<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Verify the authenticated user has the required permission slug.
     *
     * Usage in route: ->middleware('permission:inventory:create')
     */
    public function handle(Request $request, Closure $next, string $permissionSlug): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $request->user()->hasPermission($permissionSlug)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission.',
                'required_permission' => $permissionSlug,
            ], 403);
        }

        return $next($request);
    }
}
