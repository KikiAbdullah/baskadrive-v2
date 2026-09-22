<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends ApiController
{
    /**
     * Create a new AuthController instance.
     *
     * Semua endpoint kecuali `login` dan `refresh` memerlukan JWT
     * yang valid pada header `Authorization: Bearer <token>`.
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'refresh']]);
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

        $credentials = $request->only('username', 'password');

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(responseFailed('Username or Password is incorrect'), 401);
        }

        return $this->respondWithToken($token, auth('api')->user());
    }

    /**
     * Get the authenticated User.
     */
    public function me(): JsonResponse
    {
        return response()->json(responseSuccess(auth('api')->user()));
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(responseSuccess([], 'Successfully logged out'));
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();

            return response()->json(responseSuccess([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ], 'Token refreshed'));
        } catch (JWTException $e) {
            return response()->json(responseFailed('Token tidak dapat diperbarui. Silakan login kembali.'), 401);
        }
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token, User $user): JsonResponse
    {
        // Permission mobile dipakai SessionManager di sisi Flutter.
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        return response()->json(responseSuccess([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
                'permissions' => $permissions,
            ],
        ], 'Login Berhasil'));
    }
}
