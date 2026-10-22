<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin', 'customer', 'reseller') NOT NULL DEFAULT 'customer'");
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('customer')->change();
            });
        }

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->enum('discount_type', ['fixed', 'percentage']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('minimum_order_amount', 12, 2)->default(0);
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('store_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->string('value', 255);
            $table->timestamps();
        });

        DB::table('store_settings')->insert([
            'key' => 'delivery_charge',
            'value' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('user_id')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->decimal('subtotal', 12, 2)->default(0)->after('total');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('delivery_charge', 10, 2)->default(0)->after('discount_amount');
        });

        DB::table('orders')->update(['subtotal' => DB::raw('total')]);

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['coupon_code', 'subtotal', 'discount_amount', 'delivery_charge']);
        });

        Schema::dropIfExists('store_settings');
        Schema::dropIfExists('coupons');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('users')->where('role', 'reseller')->update(['role' => 'customer']);
            DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin', 'customer') NOT NULL DEFAULT 'customer'");
        } elseif (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::table('users')->where('role', 'reseller')->update(['role' => 'customer']);
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['super_admin', 'customer'])->default('customer')->change();
            });
        }
    }
};
