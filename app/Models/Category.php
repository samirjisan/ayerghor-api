<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'image', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function getImageAttribute(?string $value): ?string
    {
        if (! $value || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return url(Storage::disk('public')->url($value));
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
