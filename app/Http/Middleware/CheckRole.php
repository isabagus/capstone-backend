<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TSK-S1-05: CheckRole Middleware
 * Memastikan user yang terautentikasi memiliki role yang diizinkan.
 * Penggunaan: ->middleware('role:owner,manager')
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Daftar role yang diizinkan (pisah koma)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->hasAnyRole($roles)) {
            return response()->json([
                'message'  => 'Akses ditolak. Role Anda tidak memiliki izin untuk endpoint ini.',
                'required' => $roles,
                'current'  => $user->role?->name,
            ], 403);
        }

        return $next($request);
    }
}
