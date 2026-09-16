<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user and authenticate them.
     */
    public function register(
        RegisterRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(
                $validated['password']
            ),
        ]);

        Auth::login($user);

        $request->session()->regenerate();

        return response()->json([
            'success' => true,

            'message' =>
                'Account created successfully.',

            'user' => $user,
        ], 201);
    }

    /**
     * Authenticate an existing user.
     */
    public function login(
        LoginRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        $remember =
            $validated['remember'] ?? false;

        if (! Auth::attempt(
            $credentials,
            $remember
        )) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The provided email or password is incorrect.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'success' => true,

            'message' =>
                'Logged in successfully.',

            'user' => $request->user(),
        ]);
    }

    /**
     * Return the currently authenticated user.
     */
    public function user(
        Request $request
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    }

    /**
     * Log the current user out.
     */
    public function logout(
        Request $request
    ): JsonResponse {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()
            ->regenerateToken();

        return response()->json([
            'success' => true,
            'message' =>
                'Logged out successfully.',
        ]);
    }
}