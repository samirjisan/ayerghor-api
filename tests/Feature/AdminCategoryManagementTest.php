<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_access_category_management(): void
    {
        $this->getJson('/api/admin/categories')->assertUnauthorized();

        $customer = $this->createUser('01730303030');
        Sanctum::actingAs($customer);

        $this->postJson('/api/admin/categories', ['name' => 'Decor'])->assertForbidden();
    }

    public function test_super_admin_can_create_update_and_delete_categories_with_images(): void
    {
        Storage::fake('public');
        $admin = $this->createUser('01740404040', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->post('/api/admin/categories', [
            'name' => 'Home Decor',
            'is_active' => true,
            'image' => UploadedFile::fake()->create('decor.png', 1, 'image/png'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('name', 'Home Decor')
            ->assertJsonPath('slug', 'home-decor');

        $category = Category::firstOrFail();
        $imagePath = $category->getRawOriginal('image');
        Storage::disk('public')->assertExists($imagePath);

        $this->patchJson("/api/admin/categories/{$category->id}", [
            'name' => 'Wall Decor',
            'is_active' => false,
            'remove_image' => true,
        ])->assertOk()
            ->assertJsonPath('name', 'Wall Decor')
            ->assertJsonPath('slug', 'wall-decor')
            ->assertJsonPath('is_active', false)
            ->assertJsonPath('image', null);

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Wall Decor',
            'slug' => 'wall-decor',
            'image' => null,
            'is_active' => false,
        ]);

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_can_list_inactive_categories_while_public_listing_remains_active_only(): void
    {
        Category::create(['name' => 'Visible', 'slug' => 'visible', 'is_active' => true]);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);
        $admin = $this->createUser('01750505050', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/categories')->assertOk()->assertJsonCount(2);
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Decor', 'slug' => 'decor']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Table lamp',
            'slug' => 'table-lamp',
            'price' => 500,
            'stock' => 1,
        ]);
        $admin = $this->createUser('01760606060', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Categories with products cannot be deleted. Reassign or delete their products first.');

        $this->assertModelExists($category);
        $this->assertDatabaseHas('products', ['slug' => 'table-lamp']);
    }

    private function createUser(string $mobile, string $role = User::ROLE_CUSTOMER): User
    {
        return User::create([
            'name' => 'Test User',
            'mobile' => $mobile,
            'password' => 'password123',
            'role' => $role,
        ])->refresh();
    }
}
