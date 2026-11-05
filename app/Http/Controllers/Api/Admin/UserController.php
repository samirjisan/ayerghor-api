<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereIn('role', [User::ROLE_CUSTOMER, User::ROLE_RESELLER])
            ->with([
                'profile.district:id,name,bn_name',
                'profile.thana:id,district_id,name,bn_name',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        $users->through(fn (User $user): array => $this->userPayload($user));

        return response()->json($users);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mobile' => [
                'required',
                'regex:/^01[3-9][0-9]{8}$/',
                Rule::unique('users', 'mobile')->ignore($user->id),
            ],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'facebook_id' => ['nullable', 'string', 'max:150'],
            'village_holding' => ['required', 'string', 'max:255'],
            'post_office_area' => ['required', 'string', 'max:255'],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'thana_id' => [
                'required',
                'integer',
                Rule::exists('thanas', 'id')->where('district_id', $request->input('district_id')),
            ],
        ]);

        DB::transaction(function () use ($user, $data): void {
            $user->update([
                'name' => $data['name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'facebook_id' => $data['facebook_id'] ?? null,
            ]);

            $user->profile()->updateOrCreate([], [
                'village_holding' => $data['village_holding'],
                'post_office_area' => $data['post_office_area'],
                'district_id' => $data['district_id'],
                'thana_id' => $data['thana_id'],
            ]);
        });

        return response()->json($this->userPayload($user->fresh()->load([
            'profile.district:id,name,bn_name',
            'profile.thana:id,district_id,name,bn_name',
        ])));
    }

    public function updateStatus(Request $request, User $user): JsonResponse
    {
        $this->ensureManageable($user);

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->update(['is_active' => $data['is_active']]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active ? 'User account activated.' : 'User account deactivated.',
            'user' => $this->userPayload($user->fresh()->load([
                'profile.district:id,name,bn_name',
                'profile.thana:id,district_id,name,bn_name',
            ])),
        ]);
    }

    private function ensureManageable(User $user): void
    {
        abort_unless(in_array($user->role, [User::ROLE_CUSTOMER, User::ROLE_RESELLER], true), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'facebook_id' => $user->facebook_id,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'last_login_at' => $user->last_login_at,
            'profile' => $user->profile ? [
                'village_holding' => $user->profile->village_holding,
                'post_office_area' => $user->profile->post_office_area,
                'district' => $user->profile->district?->only(['id', 'name', 'bn_name']),
                'thana' => $user->profile->thana?->only(['id', 'name', 'bn_name']),
            ] : null,
        ];
    }
}
