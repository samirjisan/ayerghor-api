<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Customer registration. Super admin can NOT be created from here (role is forced to "customer").
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'        => $data['name'],
                'mobile'      => $data['mobile'],
                'email'       => $data['email'] ?? null,
                'facebook_id' => $data['facebook_id'] ?? null,
                'password'    => $data['password'],        // hashed by the model cast
                'role'        => $data['role'] ?? User::ROLE_CUSTOMER,
                'preferred_language' => $data['preferred_language'] ?? 'en',
            ]);

            CustomerProfile::create([
                'user_id'          => $user->id,
                'village_holding'  => $data['village_holding'],
                'post_office_area' => $data['post_office_area'],
                'thana_id'         => $data['thana_id'],
                'district_id'      => $data['district_id'],
            ]);

            return $user;
        });

        $token = $user->createToken('ayerghor-web')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $this->userPayload($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Login with mobile number (= user id) and password. Works for both super admin and customer.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('mobile', $request->mobile)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The mobile number or password is incorrect.'], 422);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account is inactive. Please contact support.'], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('ayerghor-web')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $this->userPayload($user),
            'token'   => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function updateLanguage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferred_language' => ['required', 'in:en,bn'],
        ]);

        $request->user()->update($data);

        return response()->json(['preferred_language' => $request->user()->preferred_language]);
    }

    private function userPayload(User $user): array
    {
        $user->load('profile.district', 'profile.thana');

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'mobile'      => $user->mobile,
            'email'       => $user->email,
            'facebook_id' => $user->facebook_id,
            'role'        => $user->role,
            'preferred_language' => $user->preferred_language ?? 'en',
            'profile'     => $user->profile ? [
                'village_holding'  => $user->profile->village_holding,
                'post_office_area' => $user->profile->post_office_area,
                'district'         => $user->profile->district?->only(['id', 'name', 'bn_name']),
                'thana'            => $user->profile->thana?->only(['id', 'name', 'bn_name']),
            ] : null,
        ];
    }
}
