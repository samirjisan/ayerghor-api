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

class AdminMarketplaceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_create_update_and_delete_products_with_uploaded_images(): void
    {
        Storage::fake('public');
        $customer = $this->createUser('01730303030');
        $category = Category::create(['name' => 'Decor', 'slug' => 'decor']);
        Sanctum::actingAs($customer);

        $this->postJson('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Lamp',
            'price' => 600,
            'stock' => 3,
        ])->assertForbidden();

        $admin = $this->createUser('01740404040', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/products', [
            'category_id' => $category->id,
            'name' => 'Lamp',
            'price' => 600,
            'sale_price' => 500,
            'stock' => 3,
            'is_active' => true,
            'image' => UploadedFile::fake()->create('lamp.png', 1, 'image/png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $product = Product::firstOrFail();
        $imagePath = $product->getRawOriginal('image');
        Storage::disk('public')->assertExists($imagePath);
        $response->assertJsonPath('name', 'Lamp')
            ->assertJsonPath('sale_price', '500.00');
        $this->assertModelExists($product);

        $this->patchJson("/api/admin/products/{$product->id}", [
            'category_id' => $category->id,
            'name' => 'Updated lamp',
            'price' => 700,
            'sale_price' => null,
            'stock' => 2,
            'remove_image' => true,
        ])->assertOk()->assertJsonPath('name', 'Updated lamp');

        Storage::disk('public')->assertMissing($imagePath);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated lamp',
            'price' => '700.00',
            'image' => null,
        ]);

        $this->deleteJson("/api/admin/products/{$product->id}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_save_delivery_charge_and_manage_coupon_rules(): void
    {
        $admin = $this->createUser('01750505050', User::ROLE_SUPER_ADMIN);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/settings', ['delivery_charge' => 65])
            ->assertOk()
            ->assertJsonPath('delivery_charge', 65);

        $this->postJson('/api/admin/coupons', [
            'code' => ' summer ',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'minimum_order_amount' => 500,
            'maximum_discount' => 100,
            'usage_limit' => 20,
            'per_user_limit' => 1,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('code', 'SUMMER');

        $this->postJson('/api/admin/coupons', [
            'code' => ' summer ',
            'discount_type' => 'fixed',
            'discount_value' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertDatabaseHas('store_settings', ['key' => 'delivery_charge', 'value' => '65.00']);
        $this->assertDatabaseHas('coupons', [
            'code' => 'SUMMER',
            'discount_type' => 'percentage',
            'discount_value' => '15.00',
        ]);
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
