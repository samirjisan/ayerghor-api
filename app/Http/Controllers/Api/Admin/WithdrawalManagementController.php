<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalManagementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            WithdrawalRequest::with(['user:id,name,mobile', 'product:id,name'])->latest()->paginate(20)
        );
    }

    public function update(Request $request, WithdrawalRequest $withdrawal): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected,completed'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $withdrawal = DB::transaction(function () use ($data, $withdrawal) {
            $withdrawal = WithdrawalRequest::lockForUpdate()->findOrFail($withdrawal->id);
            $allowed = [
                'pending' => ['approved', 'rejected'],
                'approved' => ['completed'],
                'rejected' => [],
                'completed' => [],
            ];

            if (! in_array($data['status'], $allowed[$withdrawal->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ['This withdrawal status transition is not allowed.'],
                ]);
            }

            if ($data['status'] === 'completed') {
                $user = User::whereKey($withdrawal->user_id)->lockForUpdate()->firstOrFail();

                if ($withdrawal->type === 'cash') {
                    $balance = (float) WalletTransaction::where('user_id', $user->id)->sum('amount');
                    $reserved = (float) WithdrawalRequest::where('user_id', $user->id)
                        ->where('id', '!=', $withdrawal->id)
                        ->where('type', 'cash')
                        ->whereIn('status', ['pending', 'approved'])
                        ->sum('amount');

                    if ((float) $withdrawal->amount > $balance - $reserved) {
                        throw ValidationException::withMessages([
                            'status' => ['The user no longer has enough available balance for this withdrawal.'],
                        ]);
                    }
                } else {
                    $points = (int) WalletTransaction::where('user_id', $user->id)->sum('points');
                    $reserved = (int) WithdrawalRequest::where('user_id', $user->id)
                        ->where('id', '!=', $withdrawal->id)
                        ->where('type', 'product_adjustment')
                        ->whereIn('status', ['pending', 'approved'])
                        ->sum('points');

                    if ((int) $withdrawal->points > $points - $reserved) {
                        throw ValidationException::withMessages([
                            'status' => ['The user no longer has enough available points.'],
                        ]);
                    }
                }

                WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'withdrawal',
                    'amount' => $withdrawal->type === 'cash' ? -$withdrawal->amount : 0,
                    'points' => $withdrawal->type === 'product_adjustment' ? -$withdrawal->points : 0,
                    'reference_type' => WithdrawalRequest::class,
                    'reference_id' => $withdrawal->id,
                    'description' => $withdrawal->type === 'cash' ? 'Cash withdrawal completed.' : 'Points redeemed for a product.',
                ]);
            }

            $withdrawal->update([
                'status' => $data['status'],
                'delivery_fee' => $data['delivery_fee'] ?? $withdrawal->delivery_fee,
                'admin_note' => $data['admin_note'] ?? $withdrawal->admin_note,
            ]);

            return $withdrawal->fresh(['user:id,name,mobile', 'product:id,name']);
        });

        return response()->json(['message' => 'Withdrawal request updated.', 'withdrawal' => $withdrawal]);
    }
}
