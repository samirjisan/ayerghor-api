<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\MarketingAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarketingAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->marketingAccounts()->with('parentAccount:id,account_code')->latest()->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_account_code' => ['nullable', 'string', 'exists:marketing_accounts,account_code'],
        ]);

        $parent = isset($data['parent_account_code'])
            ? MarketingAccount::where('account_code', $data['parent_account_code'])->where('is_active', true)->firstOrFail()
            : null;
        $parentAccountId = $parent?->id;
        $layer = 1;

        while ($parent) {
            $layer++;
            if ($layer > 10) {
                throw ValidationException::withMessages([
                    'parent_account_code' => ['Marketing account referrals are limited to 10 layers.'],
                ]);
            }
            $parent = $parent->parentAccount;
        }

        do {
            $code = 'AGM' . Str::upper(Str::random(9));
        } while (MarketingAccount::where('account_code', $code)->exists());

        $account = $request->user()->marketingAccounts()->create([
            'parent_account_id' => $parentAccountId,
            'account_code' => $code,
        ]);

        return response()->json([
            'message' => 'Marketing account created.',
            'account' => $account->load('parentAccount:id,account_code'),
        ], 201);
    }
}
