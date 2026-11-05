<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Category::query()->orderBy('name')->get(['id', 'name', 'slug', 'image', 'is_active'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['image'] = $request->file('image')?->store('categories', 'public');
        unset($data['remove_image']);

        return response()->json(Category::create($data), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request);
        $oldImage = $category->getRawOriginal('image');

        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        unset($data['remove_image']);
        $category->update($data);

        if ($oldImage !== $category->getRawOriginal('image')) {
            $this->deleteStoredImage($oldImage);
        }

        return response()->json($category->fresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return response()->json([
                'message' => 'Categories with products cannot be deleted. Reassign or delete their products first.',
            ], 422);
        }

        $image = $category->getRawOriginal('image');
        $category->delete();
        $this->deleteStoredImage($image);

        return response()->json(['message' => 'Category deleted successfully.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $suffix = 1;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image && ! str_starts_with($image, 'http://') && ! str_starts_with($image, 'https://')) {
            Storage::disk('public')->delete($image);
        }
    }
}
