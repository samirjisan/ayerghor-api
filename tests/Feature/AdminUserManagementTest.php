<?php

namespace Tests\Feature;

use App\Models\CustomerProfile;
use App\Models\District;
use App\Models\Thana;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_list_and_manage_customer_accounts(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();

        $customer = $this->createUser('01730303030');
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->putJson("/api/admin/users/{$customer->id}", [])->assertForbidden();
        $this->patchJson("/api/admin/users/{$customer->id}/status", ['is_active' => false])->assertForbidden();
    }

    public function test_super_admin_can_view_registered_users_and_their_profile_details(): void
    {
        $customer = $this->createUser('01740404040');
        $this->createUser('01750505050', User::ROLE_RESELLER);
        $this->createUser('01760606060', User::ROLE_SUPER_ADMIN);
        $admin = $this->createUser('01770707070', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?search=01740404040')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('data.0.name', $customer->name)
            ->assertJsonPath('data.0.profile.village_holding', 'Village')
            ->assertJsonPath('data.0.profile.district.name', 'Dhaka')
            ->assertJsonPath('data.0.profile.thana.name', 'Savar')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_super_admin_can_edit_user_contact_and_address_details(): void
    {
        $customer = $this->createUser('01780808080');
        $newDistrict = District::create(['name' => 'Chattogram']);
        $newThana = Thana::create(['district_id' => $newDistrict->id, 'name' => 'Pahartali']);
        $admin = $this->createUser('01790909090', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$customer->id}", [
            'name' => 'Updated Customer',
            'mobile' => '01780808081',
            'email' => 'updated@example.com',
            'facebook_id' => 'updated.profile',
            'role' => User::ROLE_SUPER_ADMIN,
            'village_holding' => 'Updated village',
            'post_office_area' => 'Updated post office',
            'district_id' => $newDistrict->id,
            'thana_id' => $newThana->id,
        ])->assertOk()
            ->assertJsonPath('name', 'Updated Customer')
            ->assertJsonPath('mobile', '01780808081')
            ->assertJsonPath('email', 'updated@example.com')
            ->assertJsonPath('profile.district.name', 'Chattogram')
            ->assertJsonPath('profile.thana.name', 'Pahartali');

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Customer',
            'mobile' => '01780808081',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $customer->id,
            'village_holding' => 'Updated village',
            'post_office_area' => 'Updated post office',
            'district_id' => $newDistrict->id,
            'thana_id' => $newThana->id,
        ]);
    }

    public function test_super_admin_can_deactivate_and_reactivate_users_and_deactivation_revokes_tokens(): void
    {
        $customer = $this->createUser('01711112222');
        $token = $customer->createToken('customer-session')->accessToken;
        $admin = $this->createUser('01722223333', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$customer->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('user.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => false]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
        $this->postJson('/api/login', ['mobile' => $customer->mobile, 'password' => 'password123'])
            ->assertForbidden();

        $this->patchJson("/api/admin/users/{$customer->id}/status", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('user.is_active', true);

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'is_active' => true]);
    }

    public function test_user_updates_reject_duplicate_mobile_and_a_thana_from_another_district(): void
    {
        $customer = $this->createUser('01733334444');
        $otherCustomer = $this->createUser('01744445555');
        $admin = $this->createUser('01755556666', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $payload = $this->validUpdatePayload($customer);
        $payload['mobile'] = $otherCustomer->mobile;
        $this->putJson("/api/admin/users/{$customer->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mobile');

        $payload = $this->validUpdatePayload($customer);
        $payload['district_id'] = District::create(['name' => 'Other district'])->id;
        $this->putJson("/api/admin/users/{$customer->id}", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('thana_id');
    }

    public function test_super_admin_accounts_cannot_be_managed_as_customers(): void
    {
        $adminUser = $this->createUser('01766667777', User::ROLE_SUPER_ADMIN);
        $actingAdmin = $this->createUser('01777778888', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($actingAdmin);

        $this->putJson("/api/admin/users/{$adminUser->id}", [])->assertNotFound();
        $this->patchJson("/api/admin/users/{$adminUser->id}/status", ['is_active' => false])->assertNotFound();
    }

    /**
     * @return array<string, int|string|null>
     */
    private function validUpdatePayload(User $user): array
    {
        $profile = $user->profile;

        return [
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'facebook_id' => $user->facebook_id,
            'village_holding' => $profile->village_holding,
            'post_office_area' => $profile->post_office_area,
            'district_id' => $profile->district_id,
            'thana_id' => $profile->thana_id,
        ];
    }

    private function createUser(string $mobile, string $role = User::ROLE_CUSTOMER): User
    {
        $user = User::create([
            'name' => 'Test User',
            'mobile' => $mobile,
            'email' => $mobile.'@example.com',
            'password' => 'password123',
            'role' => $role,
        ])->refresh();

        if ($role !== User::ROLE_SUPER_ADMIN) {
            $district = District::firstOrCreate(['name' => 'Dhaka']);
            $thana = Thana::firstOrCreate(
                ['district_id' => $district->id, 'name' => 'Savar']
            );
            CustomerProfile::create([
                'user_id' => $user->id,
                'village_holding' => 'Village',
                'post_office_area' => 'Post office',
                'district_id' => $district->id,
                'thana_id' => $thana->id,
            ]);
        }

        return $user;
    }
}
