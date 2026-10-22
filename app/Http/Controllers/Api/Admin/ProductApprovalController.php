<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApprovalController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Product::with(['category:id,name', 'seller:id,name,mobile'])
                ->whereNotNull('seller_id')
                ->where('approval_status', 'pending')
                ->latest()
                ->paginate(15)
        );
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->seller_id !== null, 422, 'Only seller-submitted products can be reviewed here.');
        abort_unless($product->approval_status === 'pending', 422, 'This product has already been reviewed.');

        $data = $request->validate([
            'approval_status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $product->update([
            ...$data,
            'is_active' => $data['approval_status'] === 'approved',
        ]);

        return response()->json([
            'message' => $data['approval_status'] === 'approved'
                ? 'Product approved and published.'
                : 'Product rejected.',
            'product' => $product->fresh(['category:id,name', 'seller:id,name,mobile']),
        ]);
    }
}
