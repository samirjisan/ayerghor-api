<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Order::with(['user:id,name,mobile', 'items:id,order_id,product_name,quantity,subtotal,seller_id'])
                ->latest()
                ->paginate(20)
        );
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:confirmed,shipped,delivered,cancelled']]);

        $order = DB::transaction(function () use ($data, $order) {
            $order = Order::with('items.product')->lockForUpdate()->findOrFail($order->id);
            $transitions = [
                'pending' => ['confirmed', 'cancelled'],
                'confirmed' => ['shipped', 'cancelled'],
                'shipped' => ['delivered'],
                'delivered' => [],
                'cancelled' => [],
            ];

            if (! in_array($data['status'], $transitions[$order->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This order status transition is not allowed.'],
                ]);
            }

            if ($data['status'] === 'cancelled') {
                foreach ($order->items as $item) {
                    $item->product?->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => $data['status'],
                'payment_status' => $data['status'] === 'delivered' ? 'paid' : $order->payment_status,
            ]);

            return $order->fresh(['user:id,name,mobile', 'items']);
        });

        return response()->json(['message' => 'Order status updated.', 'order' => $order]);
    }
}
