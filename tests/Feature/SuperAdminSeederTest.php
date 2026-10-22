<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_initial_super_admin_from_environment_configuration(): void
    {
        config([
            'ayerghor.super_admin.mobile' => '01712345678',
            'ayerghor.super_admin.password' => 'a-configured-password',
            'ayerghor.super_admin.name' => 'Configured Admin',
            'ayerghor.super_admin.email' => 'configured@example.com',
        ]);

        app(SuperAdminSeeder::class)->run();

        $admin = User::where('mobile', '01712345678')->firstOrFail();
        $this->assertSame(User::ROLE_SUPER_ADMIN, $admin->role);
        $this->assertSame('Configured Admin', $admin->name);
        $this->assertTrue(Hash::check('a-configured-password', $admin->password));
    }

    public function test_it_requires_configured_credentials_when_no_super_admin_exists(): void
    {
        config([
            'ayerghor.super_admin.mobile' => null,
            'ayerghor.super_admin.password' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Set SUPER_ADMIN_MOBILE and SUPER_ADMIN_PASSWORD before seeding the initial super admin.'
        );

        app(SuperAdminSeeder::class)->run();
    }

    public function test_it_preserves_existing_admin_when_initial_credentials_are_not_configured(): void
    {
        $admin = User::create([
            'name' => 'Existing Admin',
            'mobile' => '01798765432',
            'password' => 'existing-password',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $originalPassword = $admin->password;
        config([
            'ayerghor.super_admin.mobile' => null,
            'ayerghor.super_admin.password' => null,
        ]);

        app(SuperAdminSeeder::class)->run();

        $this->assertSame($originalPassword, $admin->fresh()->password);
        $this->assertSame(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
    }
}
