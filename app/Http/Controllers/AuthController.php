<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * TSK-S1-05: AuthController - Menangani autentikasi API via Laravel Sanctum.
 * Endpoints: login, logout, me (user info with role).
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Autentikasi pengguna dan mengembalikan Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::with('role.permissions')->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
            ]);
        }

        // Hapus token lama sebelum membuat yang baru (single-session policy)
        $user->tokens()->delete();

        $token = $user->createToken('api-token', ['*'], now()->addDays(7))->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token'   => $token,
            'user'    => [
                'id'           => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'role'         => $user->role?->name,
                'role_display' => $user->role?->display_name,
                'permissions'  => $user->role?->permissions->pluck('name') ?? [],
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     * Cabut token Sanctum aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * GET /api/auth/me
     * Mengembalikan data pengguna yang sedang login beserta role dan permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'role'         => $user->role?->name,
            'role_display' => $user->role?->display_name,
            'permissions'  => $user->role?->permissions->pluck('name') ?? [],
        ]);
    }
}
