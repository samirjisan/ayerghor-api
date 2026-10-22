<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MarketingAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_order_tracks_seller_and_seller_can_leave_feedback_after_delivery(): void
    {
        $buyer = $this->createUser('01711111111');
        $seller = $this->createUser('01722222222');
        $admin = $this->createUser('01733333333', User::ROLE_SUPER_ADMIN);
        $category = Category::create(['name' => 'Furniture', 'slug' => 'furniture']);
        $product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => 'Desk',
            'slug' => 'desk',
            'price' => 200,
            'sale_price' => 150,
            'stock' => 4,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);

        Sanctum::actingAs($buyer);
        $this->assertAuthenticatedAs($buyer, 'sanctum');
        $this->assertSame(User::ROLE_CUSTOMER, $buyer->role);
        $this->assertTrue($buyer->is_active);
        $this->postJson('/api/customer/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();
        $this->getJson('/api/customer/cart')->assertOk()->assertJsonCount(1);
        $this->postJson('/api/customer/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'shipping_address' => 'Dhaka, Bangladesh',
        ])
            ->assertCreated()
            ->assertJsonPath('order.total', '300.00');

        $order = Order::firstOrFail();
        $item = OrderItem::firstOrFail();
        $this->assertSame($seller->id, $item->seller_id);
        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);

        Sanctum::actingAs($admin);
        foreach (['confirmed', 'shipped', 'delivered'] as $status) {
            $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('order.status', $status);
        }

        Sanctum::actingAs($seller);
        $this->assertAuthenticatedAs($seller, 'sanctum');
        $this->assertSame($seller->id, (int) $item->seller_id);
        $this->postJson("/api/customer/sales/{$item->id}/feedback", [
            'rating' => 5,
            'feedback' => 'Sale completed successfully.',
        ])
            ->assertCreated()
            ->assertJsonPath('feedback.rating', 5);
    }

    public function test_customer_language_preference_is_saved(): void
    {
        $customer = $this->createUser('01744444444');

        Sanctum::actingAs($customer);
        $this->assertAuthenticatedAs($customer, 'sanctum');
        $this->patchJson('/api/me/language', ['preferred_language' => 'bn'])
            ->assertOk()
            ->assertJsonPath('preferred_language', 'bn');

        $this->assertSame('bn', $customer->fresh()->preferred_language);
    }

    public function test_seller_products_stay_hidden_until_admin_approval(): void
    {
        $seller = $this->createUser('01755555555');
        $admin = $this->createUser('01766666666', User::ROLE_SUPER_ADMIN);
        $category = Category::create(['name' => 'Kitchen', 'slug' => 'kitchen']);

        Sanctum::actingAs($seller);
        $response = $this->postJson('/api/customer/seller-products', [
            'category_id' => $category->id,
            'name' => 'Seller kettle',
            'price' => 1200,
            'stock' => 5,
        ])->assertCreated()->assertJsonPath('product.approval_status', 'pending');

        $productId = $response->json('product.id');
        $this->getJson('/api/products')->assertJsonMissing(['id' => $productId]);
        $this->assertDatabaseCount('notifications', 1);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/seller-products/{$productId}/review", [
            'approval_status' => 'approved',
        ])->assertOk()->assertJsonPath('product.is_active', true);

        $this->getJson('/api/products')->assertJsonFragment(['id' => $productId]);
    }

    public function test_marketing_accounts_allow_unlimited_owned_ids_but_stop_at_layer_ten(): void
    {
        $owner = $this->createUser('01777777777');
        Sanctum::actingAs($owner);

        $first = $this->postJson('/api/customer/marketing-accounts', [])
            ->assertCreated()
            ->json('account');
        $second = $this->postJson('/api/customer/marketing-accounts', [])
            ->assertCreated()
            ->json('account');
        $this->assertNotSame($first['account_code'], $second['account_code']);

        $parentId = null;
        for ($layer = 1; $layer <= 10; $layer++) {
            $account = MarketingAccount::create([
                'user_id' => $owner->id,
                'parent_account_id' => $parentId,
                'account_code' => 'LAYER' . $layer . 'TEST',
            ]);
            $parentId = $account->id;
        }

        $this->postJson('/api/customer/marketing-accounts', [
            'parent_account_code' => $account->account_code,
        ])->assertUnprocessable();
    }

    public function test_withdrawal_minimum_and_cash_or_product_redemptions_are_recorded(): void
    {
        $customer = $this->createUser('01788888888');
        $admin = $this->createUser('01799999999', User::ROLE_SUPER_ADMIN);
        $category = Category::create(['name' => 'Decor', 'slug' => 'decor']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Table lamp',
            'slug' => 'table-lamp',
            'price' => 800,
            'stock' => 3,
            'is_active' => true,
            'approval_status' => 'approved',
        ]);
        WalletTransaction::create([
            'user_id' => $customer->id,
            'type' => 'sale_income',
            'amount' => 1000,
            'points' => 10,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/customer/withdrawals', ['type' => 'cash', 'amount' => 499])
            ->assertUnprocessable();
        $cashRequest = $this->postJson('/api/customer/withdrawals', ['type' => 'cash', 'amount' => 500])
            ->assertCreated()
            ->json('withdrawal');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/withdrawals/{$cashRequest['id']}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/withdrawals/{$cashRequest['id']}", ['status' => 'completed'])->assertOk();
        $this->assertSame(500.0, (float) WalletTransaction::where('user_id', $customer->id)->sum('amount'));

        Sanctum::actingAs($customer);
        $productRequest = $this->postJson('/api/customer/withdrawals', [
            'type' => 'product_adjustment',
            'product_id' => $product->id,
            'points' => 5,
        ])->assertCreated()->json('withdrawal');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/withdrawals/{$productRequest['id']}", [
            'status' => 'approved',
            'delivery_fee' => 75,
        ])->assertOk();
        $this->patchJson("/api/admin/withdrawals/{$productRequest['id']}", ['status' => 'completed'])->assertOk();
        $this->assertSame(5, (int) WalletTransaction::where('user_id', $customer->id)->sum('points'));
    }

    private function createUser(string $mobile, string $role = User::ROLE_CUSTOMER): User
    {
        $user = User::create([
            'name' => 'Test User',
            'mobile' => $mobile,
            'password' => 'password123',
            'role' => $role,
        ]);

        return $user->refresh();
    }
}
