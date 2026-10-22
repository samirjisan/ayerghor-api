<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_can_merge_guest_cart_and_checkout_with_coupon_and_delivery_charge(): void
    {
        $reseller = $this->createUser('01710101010', User::ROLE_RESELLER);
        $category = Category::create(['name' => 'Furniture', 'slug' => 'furniture']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Desk',
            'slug' => 'desk',
            'price' => 250,
            'sale_price' => 200,
            'stock' => 5,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        $coupon = Coupon::create([
            'code' => 'SAVE10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'minimum_order_amount' => 100,
            'maximum_discount' => 30,
            'is_active' => true,
        ]);
        StoreSetting::updateOrCreate(['key' => 'delivery_charge'], ['value' => '40.00']);

        Sanctum::actingAs($reseller);

        $this->postJson('/api/customer/cart/merge', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonCount(1);
        $this->postJson('/api/coupons/validate', [
            'coupon_code' => 'save10',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('discount_amount', 30);

        $this->postJson('/api/customer/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_address' => 'Dhaka, Bangladesh',
            'coupon_code' => 'save10',
        ])->assertCreated()
            ->assertJsonPath('order.subtotal', '400.00')
            ->assertJsonPath('order.discount_amount', '30.00')
            ->assertJsonPath('order.delivery_charge', '40.00')
            ->assertJsonPath('order.total', '410.00');

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $reseller->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_id' => $coupon->id,
            'user_id' => $reseller->id,
            'discount_amount' => '30.00',
        ]);
        $this->assertSame(3, (int) $product->fresh()->stock);
        $this->assertSame(1, Order::count());
        $this->assertSame(1, CouponRedemption::count());
    }

    public function test_coupon_rejection_does_not_create_an_order_or_reduce_inventory(): void
    {
        $buyer = $this->createUser('01720202020');
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Kettle',
            'slug' => 'kettle',
            'price' => 80,
            'stock' => 4,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        Coupon::create([
            'code' => 'MINIMUM',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'minimum_order_amount' => 100,
            'is_active' => true,
        ]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/customer/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'shipping_address' => 'Dhaka, Bangladesh',
            'coupon_code' => 'MINIMUM',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('coupon_code')
            ->assertJsonPath('errors.coupon_code.0', 'The order does not meet this coupon minimum.');

        $this->assertSame(0, Order::count());
        $this->assertSame(4, (int) $product->fresh()->stock);
        $this->assertSame(0, CouponRedemption::count());
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
