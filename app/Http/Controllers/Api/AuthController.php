<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum', ['except' => ['login']]);
    }

    /**
     * Get a JWT via given credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('username', $request->username)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(responseFailed('Username or Password is incorrect'), 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(responseSuccess([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 'Login Berhasil'));
    }

    /**
     * Get the authenticated User.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(responseSuccess($request->user()));
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(responseSuccess([], 'Successfully logged out'));
    }

    /**
     * Refresh a token (not applicable for Sanctum).
     */
    public function refresh(): JsonResponse
    {
        return response()->json(responseFailed('Refresh token not supported with Sanctum.'), 400);
    }
}