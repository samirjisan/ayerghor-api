<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->cartItems()->with('product.category:id,name')->latest()->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $product = $this->availableProduct($data['product_id']);

        if ($product->seller_id && (int) $product->seller_id === (int) $request->user()->id) {
            return response()->json(['message' => 'You cannot add your own seller listing to your cart.'], 422);
        }

        $item = CartItem::firstOrNew([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);
        $quantity = $item->exists ? $item->quantity + ($data['quantity'] ?? 1) : ($data['quantity'] ?? 1);

        if ($quantity > $product->stock) {
            return response()->json(['message' => 'The requested quantity exceeds available stock.'], 422);
        }

        $item->quantity = $quantity;
        $item->save();

        return response()->json(['message' => 'Product added to your cart.', 'item' => $item->load('product')], 201);
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        foreach ($data['items'] as $guestItem) {
            $product = $this->availableProduct($guestItem['product_id']);

            if ((int) $product->seller_id === (int) $request->user()->id) {
                continue;
            }

            $item = CartItem::firstOrNew([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
            ]);
            $item->quantity = min($product->stock, $item->quantity + $guestItem['quantity']);

            if ($item->quantity > 0) {
                $item->save();
            }
        }

        return $this->index($request);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless((int) $cartItem->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $product = $this->availableProduct($cartItem->product_id);
        abort_if($data['quantity'] > $product->stock, 422, 'The requested quantity exceeds available stock.');

        $cartItem->update(['quantity' => $data['quantity']]);

        return response()->json($cartItem->fresh('product'));
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless((int) $cartItem->user_id === (int) $request->user()->id, 404);
        $cartItem->delete();

        return response()->json(['message' => 'Product removed from your cart.']);
    }

    private function availableProduct(int $productId): Product
    {
        return Product::active()->where('approval_status', 'approved')->findOrFail($productId);
    }
}
