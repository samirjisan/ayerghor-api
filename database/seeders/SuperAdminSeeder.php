<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $mobile = config('ayerghor.super_admin.mobile');
        $password = config('ayerghor.super_admin.password');

        if (! $mobile || ! $password) {
            if (User::where('role', User::ROLE_SUPER_ADMIN)->exists()) {
                return;
            }

            throw new RuntimeException(
                'Set SUPER_ADMIN_MOBILE and SUPER_ADMIN_PASSWORD before seeding the initial super admin.'
            );
        }

        $user = User::firstOrNew(['mobile' => $mobile]);

        if (! $user->exists) {
            $user->password = $password;
        }

        $user->fill([
            'name' => config('ayerghor.super_admin.name'),
            'email' => config('ayerghor.super_admin.email'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ])->save();
    }
}
