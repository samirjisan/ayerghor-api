<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'customers' => User::whereIn('role', [User::ROLE_CUSTOMER, User::ROLE_RESELLER])->count(),
            'products'  => Product::count(),
            'orders'    => Order::count(),
            'pending'   => Order::where('status', 'pending')->count(),
            'pending_products' => Product::whereNotNull('seller_id')->where('approval_status', 'pending')->count(),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
            'revenue'   => (float) Order::whereIn('status', ['confirmed', 'shipped', 'delivered'])->sum('total'),
        ]);
    }
}
