<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate a user and issue access + refresh tokens.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('role.permissions')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated.'], 403);
        }

        // Revoke any existing non-expired tokens for this user
        $user->refreshTokens()->where('revoked', false)->update(['revoked' => true]);

        $accessToken = $user->createToken('api-token')->plainTextToken;

        $rawRefreshToken = Str::random(64);
        $user->refreshTokens()->create([
            'token_hash' => hash('sha256', $rawRefreshToken),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $rawRefreshToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'permissions' => $user->role?->permissions->pluck('slug'),
            ],
        ]);
    }

    /**
     * Issue a new access token using a valid refresh token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $tokenHash = hash('sha256', $validated['refresh_token']);
        $storedToken = RefreshToken::where('token_hash', $tokenHash)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if (! $storedToken) {
            return response()->json(['message' => 'Invalid or expired refresh token.'], 401);
        }

        $user = $storedToken->user;

        // Rotate: revoke old, issue new
        $storedToken->update(['revoked' => true]);

        $newAccessToken = $user->createToken('api-token')->plainTextToken;
        $newRawRefresh = Str::random(64);

        $user->refreshTokens()->create([
            'token_hash' => hash('sha256', $newRawRefresh),
            'expires_at' => now()->addDays(30),
            'revoked' => false,
        ]);

        return response()->json([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRawRefresh,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revoke the current user's tokens (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $request->user()->refreshTokens()->where('revoked', false)->update(['revoked' => true]);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('role.permissions');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
            'permissions' => $user->role?->permissions->pluck('slug'),
            'is_active' => $user->is_active,
        ]);
    }
}
