<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function findValid(string $code, float $subtotal, ?User $user = null, bool $lock = false): Coupon
    {
        $query = Coupon::where('code', mb_strtoupper(trim($code)));
        if ($lock) {
            $query->lockForUpdate();
        }
        $coupon = $query->first();

        if (! $coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon is invalid or inactive.']]);
        }

        if (($coupon->starts_at && $coupon->starts_at->isFuture())
            || ($coupon->expires_at && $coupon->expires_at->isPast())) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon is not currently valid.']]);
        }

        if ($subtotal < (float) $coupon->minimum_order_amount) {
            throw ValidationException::withMessages(['coupon_code' => ['The order does not meet this coupon minimum.']]);
        }

        if ($coupon->usage_limit !== null && $coupon->redemptions()->count() >= $coupon->usage_limit) {
            throw ValidationException::withMessages(['coupon_code' => ['This coupon has reached its usage limit.']]);
        }

        if ($user && $coupon->per_user_limit !== null
            && $coupon->redemptions()->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
            throw ValidationException::withMessages(['coupon_code' => ['You have already used this coupon the maximum number of times.']]);
        }

        return $coupon;
    }

    public function discount(Coupon $coupon, float $subtotal): float
    {
        $discount = $coupon->discount_type === 'percentage'
            ? round($subtotal * (float) $coupon->discount_value / 100, 2)
            : (float) $coupon->discount_value;

        if ($coupon->maximum_discount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount);
        }

        return min($subtotal, round($discount, 2));
    }
}
