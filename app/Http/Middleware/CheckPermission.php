<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TSK-S1-05: CheckPermission Middleware
 * Memastikan user yang terautentikasi memiliki permission granular tertentu (via role).
 * Penggunaan: ->middleware('permission:inventory.view')
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  Daftar permission yang dibutuhkan
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Cek apakah user memiliki SALAH SATU dari permissions yang diminta
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message'  => 'Akses ditolak. Anda tidak memiliki permission untuk operasi ini.',
            'required' => $permissions,
            'role'     => $user->role?->name,
        ], 403);
    }
}
