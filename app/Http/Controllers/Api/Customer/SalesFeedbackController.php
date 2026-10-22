<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesFeedbackController extends Controller
{
    public function store(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless((int) $orderItem->seller_id === (int) $request->user()->id, 403, 'Only the seller for this sale can provide feedback.');
        abort_unless($orderItem->order()->where('status', 'delivered')->exists(), 422, 'Feedback is available after delivery.');
        abort_if($orderItem->feedback()->exists(), 409, 'Feedback has already been submitted for this sale.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'feedback' => ['required', 'string', 'max:2000'],
        ]);

        $feedback = $orderItem->feedback()->create([
            ...$data,
            'seller_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Sales feedback submitted.', 'feedback' => $feedback], 201);
    }
}
