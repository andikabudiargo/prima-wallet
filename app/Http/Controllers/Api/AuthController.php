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
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $identifier = trim($request->login);
        $user = User::where('email', $identifier)
            ->orWhere('prima_id', strtolower($identifier))
            ->first();

        if (! $user || ! $user->password || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email/Prima ID atau password salah'], 401);
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

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'prima_id' => [
                'nullable', 'string', 'min:4', 'max:20', 'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,prima_id,'.$user->id,
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Hanya sentuh prima_id kalau field-nya memang dikirim - string kosong
        // berarti sengaja dikosongkan, tidak dikirim sama sekali berarti
        // biarkan nilai lama tetap ada.
        if ($request->has('prima_id')) {
            $data['prima_id'] = $request->prima_id === '' ? null : strtolower($request->prima_id);
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    /**
     * Foto profil - dipisah dari updateProfile karena butuh multipart upload.
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'avatar_path' => $request->file('avatar')->store('avatars', 'public'),
        ]);

        return response()->json($user->fresh());
    }

    /**
     * Profil usaha dipakai sebagai kop invoice (nama, tagline, alamat, no.
     * HP, logo) - diisi sekali di sini, dipakai berulang tiap generate
     * invoice baru.
     */
    public function updateBusinessProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'business_name' => 'nullable|string|max:150',
            'business_tagline' => 'nullable|string|max:150',
            'business_address' => 'nullable|string|max:255',
            'business_phone' => 'nullable|string|max:30',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = [
            'business_name' => $request->business_name,
            'business_tagline' => $request->business_tagline,
            'business_address' => $request->business_address,
            'business_phone' => $request->business_phone,
        ];

        if ($request->hasFile('logo')) {
            $data['business_logo_path'] = $request->file('logo')->store('business-logo', 'public');
        }

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
        if ($user->password) {
            $rules['current_password'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($user->password && ! Hash::check($request->current_password, $user->password)) {
            return response()->json(['errors' => ['current_password' => ['Password saat ini salah']]], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password berhasil diubah']);
    }
}