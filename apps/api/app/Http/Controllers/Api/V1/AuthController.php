<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:256'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak valid atau akun tidak aktif.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'])->plainTextToken;

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => config('sanctum.expiration') * 60,
            'user' => $user->load('roles:id,name,label'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesi berhasil diakhiri.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $studentId = $user->hasRole('student')
            ? $user->linkedStudent()->where('status', 'verified')->value('student_id')
            : null;

        return response()->json([
            'user' => $user->load('roles:id,name,label'),
            'student_id' => $studentId,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);
        $request->merge(['email' => mb_strtolower($request->string('email')->toString())]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Jika akun dapat menerima reset, instruksi akan dikirim ke alamat terdaftar.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $credentials['email'] = mb_strtolower($credentials['email']);

        $status = Password::reset($credentials, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Tautan reset tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }
}
