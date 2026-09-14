<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah'], 401);
        }

        $token = $user->createToken('flutter-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function googleLogin(Request $request)
{
    $request->validate([
        'id_token' => 'required|string',
    ]);

    $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
        'id_token' => $request->id_token,
    ]);

    if (! $response->successful()) {
        return response()->json(['message' => 'Token Google tidak valid'], 401);
    }

    $payload = $response->json();

    // Pastikan token ini memang untuk aplikasi kamu
    if ($payload['aud'] !== config('services.google.client_id')) {
        return response()->json(['message' => 'Token Google tidak valid'], 401);
    }

    $user = User::updateOrCreate(
        ['email' => $payload['email']],
        [
            'name' => $payload['name'] ?? $payload['email'],
            'google_id' => $payload['sub'],
        ]
    );

    $token = $user->createToken('flutter-app')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ]);
}

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}