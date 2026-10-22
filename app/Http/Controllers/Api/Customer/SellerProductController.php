<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Notifications\SellerProductSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SellerProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->submittedProducts()->with('category:id,name')->latest()->paginate(10)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'sale_price' => ['nullable', 'numeric', 'min:0.01', 'lte:price'],
            'stock' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        abort_unless(Category::whereKey($data['category_id'])->where('is_active', true)->exists(), 422, 'The selected category is unavailable.');

        $product = $request->user()->submittedProducts()->create([
            ...$data,
            'image' => $request->file('image')?->store('products', 'public'),
            'slug' => $this->uniqueSlug($data['name']),
            'is_active' => false,
            'approval_status' => 'pending',
        ]);

        Notification::send(
            User::where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)->get(),
            new SellerProductSubmitted($product)
        );

        return response()->json([
            'message' => 'Your product has been submitted for admin review.',
            'product' => $product->load('category:id,name'),
        ], 201);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
