<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\OrderItem;
use App\Models\WalletTransaction;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = WalletTransaction::where('user_id', $user->id)->latest()->limit(10)->get();
        $sales = OrderItem::with(['order:id,order_no,user_id,status,created_at', 'product:id,name', 'feedback:id,order_item_id,rating,feedback'])
            ->where('seller_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();
        $purchases = $user->orders()->with('items:id,order_id,product_name,price,quantity,subtotal')
            ->latest()
            ->limit(10)
            ->get(['id', 'order_no', 'status', 'total', 'created_at']);
        $withdrawals = $user->withdrawalRequests()->with('product:id,name')->latest()->limit(10)->get();
        $incomeHistory = Commission::with('account:id,account_code')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'sales' => OrderItem::where('seller_id', $user->id)->count(),
                'pending_products' => $user->submittedProducts()->where('approval_status', 'pending')->count(),
                'approved_products' => $user->submittedProducts()->where('approval_status', 'approved')->count(),
                'purchases' => $user->orders()->count(),
                'balance' => (float) WalletTransaction::where('user_id', $user->id)->sum('amount'),
                'points' => (int) WalletTransaction::where('user_id', $user->id)->sum('points'),
                'pending_withdrawals' => $user->withdrawalRequests()->where('status', 'pending')->count(),
            ],
            'products' => $user->submittedProducts()->with('category:id,name')->latest()->limit(10)->get(),
            'sales_history' => $sales,
            'purchase_history' => $purchases,
            'withdrawals' => $withdrawals,
            'income_history' => $incomeHistory,
            'transactions' => $transactions,
        ]);
    }
}
