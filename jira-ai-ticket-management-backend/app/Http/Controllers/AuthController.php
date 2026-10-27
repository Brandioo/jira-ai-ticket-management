<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'],
            'role' => 'employee',
            'api_token' => Str::random(64),
        ]);

        return response()->json([
            'token' => $user->api_token,
            'user' => $this->publicUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', Str::lower(trim($data['email'])))->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->forceFill(['api_token' => Str::random(64)])->save();

        return response()->json([
            'token' => $user->api_token,
            'user' => $this->publicUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->publicUser($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Logged out.']);
    }

    private function publicUser(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }
}
