<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name') ?? $request->validated('company_name'),
            'company_name' => $request->validated('company_name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'subscription_plan' => 'free',
            'subscription_status' => 'active',
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Login a user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUserResponse($user),
            'token' => $token,
        ]);
    }

    /**
     * Request a password reset token.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')
                ->where('user_id', $user->id)
                ->delete();

            DB::table('password_reset_tokens')->insert([
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => now()->addHour(),
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Password reset email sent successfully',
        ]);
    }

    /**
     * Reset the user's password using a token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $tokenRow = DB::table('password_reset_tokens')
            ->where('token', $request->validated('token'))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRow) {
            return response()->json([
                'error' => 'Invalid or expired token',
            ], 400);
        }

        $user = User::find($tokenRow->user_id);
        if (!$user) {
            return response()->json([
                'error' => 'Invalid or expired token',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        DB::table('password_reset_tokens')
            ->where('id', $tokenRow->id)
            ->update(['used_at' => now()]);

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    /**
     * Get the authenticated user profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json($this->formatUserResponse($request->user()));
    }

    /**
     * Update the authenticated user profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('new_password')) {
            if (!$request->filled('current_password') || !Hash::check($request->validated('current_password'), $user->password)) {
                return response()->json([
                    'error' => 'Current password is incorrect',
                ], 400);
            }

            $user->password = Hash::make($request->validated('new_password'));
        }

        if ($request->filled('email')) {
            $user->email = $request->validated('email');
        }

        if ($request->filled('company_name')) {
            $user->company_name = $request->validated('company_name');
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $this->formatUserResponse($user),
        ]);
    }

    /**
     * Delete the authenticated user account.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }

    /**
     * Format user response payload with usage info.
     */
    private function formatUserResponse(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'company_name' => $user->company_name,
            'created_at' => $user->created_at,
            'subscription_plan' => $user->subscription_plan,
            'subscription_status' => $user->subscription_status,
            'subscription_id' => $user->subscription_id,
            'usage' => [
                'invoices_count' => $user->invoices()->count(),
                'clients_count' => $user->clients()->count(),
                'storage_used_mb' => 0,
            ],
        ];
    }
}
