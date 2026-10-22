<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\CouponRedemption;
use App\Models\StoreSetting;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->orders()->with('items:id,order_id,product_name,price,quantity,subtotal,seller_id')
                ->latest()
                ->paginate(10)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'payment_method' => ['sometimes', 'in:cod'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = DB::transaction(function () use ($data, $request) {
            $order = Order::create([
                'order_no' => $this->orderNumber(),
                'user_id' => $request->user()->id,
                'status' => 'pending',
                'total' => 0,
                'shipping_address' => $data['shipping_address'],
                'payment_method' => $data['payment_method'] ?? 'cod',
                'note' => $data['note'] ?? null,
            ]);
            $subtotal = 0;

            foreach ($data['items'] as $line) {
                $product = Product::active()
                    ->where('approval_status', 'approved')
                    ->lockForUpdate()
                    ->findOrFail($line['product_id']);

                if ($product->stock < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["There is not enough stock for {$product->name}."],
                    ]);
                }

                if ((int) $product->seller_id === (int) $request->user()->id) {
                    throw ValidationException::withMessages([
                        'items' => ['You cannot purchase your own seller listing.'],
                    ]);
                }

                $unitPrice = (float) ($product->sale_price ?? $product->price);
                $lineSubtotal = $unitPrice * $line['quantity'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_name' => $product->name,
                    'price' => $unitPrice,
                    'quantity' => $line['quantity'],
                    'subtotal' => $lineSubtotal,
                ]);
                $product->decrement('stock', $line['quantity']);
                $subtotal += $lineSubtotal;
            }

            $discount = 0;
            $coupon = null;
            if (! empty($data['coupon_code'])) {
                $coupon = app(CouponService::class)->findValid(
                    $data['coupon_code'],
                    $subtotal,
                    $request->user(),
                    true
                );
                $discount = app(CouponService::class)->discount($coupon, $subtotal);
            }

            $deliveryCharge = (float) (StoreSetting::whereKey('delivery_charge')->value('value') ?? 0);
            $order->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'delivery_charge' => $deliveryCharge,
                'coupon_id' => $coupon?->id,
                'coupon_code' => $coupon?->code,
                'total' => max(0, $subtotal - $discount) + $deliveryCharge,
            ]);

            if ($coupon) {
                CouponRedemption::create([
                    'coupon_id' => $coupon->id,
                    'user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'discount_amount' => $discount,
                ]);
            }

            CartItem::where('user_id', $request->user()->id)
                ->whereIn('product_id', collect($data['items'])->pluck('product_id'))
                ->delete();

            return $order->load(['items', 'coupon']);
        });

        return response()->json([
            'message' => 'Your order has been placed.',
            'order' => $order,
        ], 201);
    }

    private function orderNumber(): string
    {
        do {
            $number = 'AG-' . Str::upper(Str::random(10));
        } while (Order::where('order_no', $number)->exists());

        return $number;
    }
}
