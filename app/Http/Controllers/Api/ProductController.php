<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no login needed) product listing used by the landing page and shop.
 */
class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category:id,name,slug')
            ->active()
            ->where('approval_status', 'approved')
            ->where('stock', '>', 0)
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category', fn ($c) => $c->where('slug', $request->category)
            ))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate($request->integer('per_page', 12));

        return response()->json($products);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with('category:id,name,slug')->active()->where('slug', $slug)->firstOrFail();

        return response()->json($product);
    }
}
