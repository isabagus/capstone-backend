<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Verify the authenticated user has one of the allowed role slugs.
     *
     * Usage in route: ->middleware('role:owner,manager')
     */
    public function handle(Request $request, Closure $next, string ...$roleSlugs): Response
    {
        if (! $request->user()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userRoleSlug = $request->user()->role?->slug;

        if (! in_array($userRoleSlug, $roleSlugs, strict: true)) {
            return response()->json([
                'message' => 'Forbidden. Your role does not have access to this resource.',
                'allowed_roles' => $roleSlugs,
            ], 403);
        }

        return $next($request);
    }
}
