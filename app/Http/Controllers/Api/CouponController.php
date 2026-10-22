<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function validateCoupon(Request $request, CouponService $coupons): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::active()->where('approval_status', 'approved')
            ->whereIn('id', collect($data['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');
        $subtotal = 0;

        foreach ($data['items'] as $item) {
            $product = $products->get($item['product_id']);
            if (! $product || $product->stock < $item['quantity']) {
                throw ValidationException::withMessages(['items' => ['A product in your cart is unavailable or has insufficient stock.']]);
            }
            $subtotal += (float) ($product->sale_price ?? $product->price) * $item['quantity'];
        }

        $coupon = $coupons->findValid($data['coupon_code'], $subtotal, $request->user());

        return response()->json([
            'coupon_code' => $coupon->code,
            'subtotal' => round($subtotal, 2),
            'discount_amount' => $coupons->discount($coupon, $subtotal),
        ]);
    }
}
