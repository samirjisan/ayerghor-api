<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\Admin\OrderManagementController;
use App\Http\Controllers\Api\Admin\ProductApprovalController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\WithdrawalManagementController;
use App\Http\Controllers\Api\Admin\StoreSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\MarketingAccountController;
use App\Http\Controllers\Api\Customer\SellerProductController;
use App\Http\Controllers\Api\Customer\SalesFeedbackController;
use App\Http\Controllers\Api\Customer\WithdrawalController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes (no login)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::get('/districts', [LocationController::class, 'districts']);
Route::get('/districts/{district}/thanas', [LocationController::class, 'thanas']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::post('/coupons/validate', [CouponController::class, 'validateCoupon']);
Route::get('/storefront/settings', [StorefrontController::class, 'settings']);

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
| Logged-in users
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/me/language', [AuthController::class, 'updateLanguage']);

    Route::middleware('role:customer,reseller')->prefix('customer')->group(function () {
        Route::get('/dashboard', [CustomerDashboardController::class, 'index']);
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart', [CartController::class, 'store']);
        Route::post('/cart/merge', [CartController::class, 'merge']);
        Route::patch('/cart/{cartItem}', [CartController::class, 'update']);
        Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);
        Route::apiResource('orders', OrderController::class)->only(['index', 'store']);
        Route::get('/marketing-accounts', [MarketingAccountController::class, 'index']);
        Route::post('/marketing-accounts', [MarketingAccountController::class, 'store']);
        Route::apiResource('seller-products', SellerProductController::class)->only(['index', 'store']);
        Route::post('/sales/{orderItem}/feedback', [SalesFeedbackController::class, 'store']);
        Route::get('/withdrawals', [WithdrawalController::class, 'index']);
        Route::post('/withdrawals', [WithdrawalController::class, 'store']);
    });

    Route::middleware('role:super_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/orders', [OrderManagementController::class, 'index']);
        Route::patch('/orders/{order}/status', [OrderManagementController::class, 'updateStatus']);
        Route::get('/withdrawals', [WithdrawalManagementController::class, 'index']);
        Route::patch('/withdrawals/{withdrawal}', [WithdrawalManagementController::class, 'update']);
        Route::apiResource('products', AdminProductController::class)->except(['show']);
        Route::apiResource('coupons', AdminCouponController::class)->except(['show']);
        Route::get('/settings', [StoreSettingsController::class, 'show']);
        Route::patch('/settings', [StoreSettingsController::class, 'update']);
        Route::get('/seller-products/pending', [ProductApprovalController::class, 'index']);
        Route::patch('/seller-products/{product}/review', [ProductApprovalController::class, 'update']);
    });
});
