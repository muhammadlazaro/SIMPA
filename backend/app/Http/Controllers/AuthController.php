<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Http\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = (string) $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            // Log failed login attempt
            Log::warning('Failed login attempt', [
                'email' => $email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid.'],
            ]);
        }

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Log successful login
        Log::info('User logged in', [
            'user_id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'role' => $user->getAttribute('role'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
                'role' => $user->getAttribute('role'),
            ]
        ], 'Login berhasil');
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Log logout
        Log::info('User logged out', [
            'user_id' => $user?->getKey(),
            'email' => $user?->getAttribute('email')
        ]);
        
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout berhasil');
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return ApiResponse::success([
            'id' => $user?->getKey(),
            'name' => $user?->getAttribute('name'),
            'email' => $user?->getAttribute('email'),
            'role' => $user?->getAttribute('role'),
        ]);
    }

    /**
     * Register new user (admin sistem only, protected by role middleware)
     */
    public function register(Request $request): JsonResponse
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required', 'string', 'max:128', 'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'role' => ['required', new Enum(UserRole::class)],
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make((string) $request->input('password')),
            'role' => $request->input('role'),
        ]);

        // Log registrasi user baru (OWASP: Log role-management functions)
        Log::info('User registered', [
            'new_user_id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'role' => $user->getAttribute('role'),
            'registered_by' => $request->user()?->getKey(),
            'ip' => $request->ip(),
        ]);

        return ApiResponse::created([
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'role' => $user->getAttribute('role'),
        ], 'User berhasil dibuat');
    }
}
