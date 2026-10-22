<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->withdrawalRequests()->with('product:id,name')->latest()->paginate(10));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:cash,product_adjustment'],
            'amount' => ['required_if:type,cash', 'nullable', 'numeric', 'min:500'],
            'product_id' => ['required_if:type,product_adjustment', 'nullable', 'exists:products,id'],
            'points' => ['required_if:type,product_adjustment', 'nullable', 'integer', 'min:1'],
        ]);

        if ($data['type'] === 'cash') {
            $user = $request->user();
            $available = (float) WalletTransaction::where('user_id', $user->id)->sum('amount');
            $reserved = (float) WithdrawalRequest::where('user_id', $user->id)
                ->where('type', 'cash')
                ->whereIn('status', ['pending', 'approved'])
                ->sum('amount');

            if ($data['amount'] > $available - $reserved) {
                return response()->json(['message' => 'The requested amount exceeds your available cash balance.'], 422);
            }
        } else {
            Product::whereKey($data['product_id'])
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->firstOrFail();

            $user = $request->user();
            $availablePoints = (int) WalletTransaction::where('user_id', $user->id)->sum('points');
            $reservedPoints = (int) WithdrawalRequest::where('user_id', $user->id)
                ->where('type', 'product_adjustment')
                ->whereIn('status', ['pending', 'approved'])
                ->sum('points');

            if ($data['points'] > $availablePoints - $reservedPoints) {
                return response()->json(['message' => 'The requested points exceed your available points balance.'], 422);
            }
        }

        $withdrawal = $request->user()->withdrawalRequests()->create([
            'type' => $data['type'],
            'amount' => $data['amount'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'points' => $data['points'] ?? null,
        ]);

        return response()->json([
            'message' => 'Your withdrawal request has been submitted for review.',
            'withdrawal' => $withdrawal,
        ], 201);
    }
}
