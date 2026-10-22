<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Coupon::latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->validateDiscountValue($data);
        $data['code'] = mb_strtoupper(trim($data['code']));

        return response()->json(Coupon::create($data), 201);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $this->validated($request, $coupon);
        $this->validateDiscountValue($data);
        $data['code'] = mb_strtoupper(trim($data['code']));
        $coupon->update($data);

        return response()->json($coupon->fresh());
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        if ($coupon->redemptions()->exists()) {
            return response()->json(['message' => 'A redeemed coupon cannot be deleted. Deactivate it instead.'], 422);
        }

        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted.']);
    }

    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        if ($request->has('code')) {
            $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'discount_type' => ['required', 'in:fixed,percentage'],
            'discount_value' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'minimum_order_amount' => ['sometimes', 'numeric', 'min:0', 'max:10000000'],
            'maximum_discount' => ['nullable', 'numeric', 'gt:0', 'max:10000000'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateDiscountValue(array $data): void
    {
        if ($data['discount_type'] === 'percentage' && (float) $data['discount_value'] > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['Percentage discounts cannot exceed 100%.'],
            ]);
        }
    }
}
