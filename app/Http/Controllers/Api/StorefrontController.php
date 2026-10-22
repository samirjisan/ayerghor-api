<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;

class StorefrontController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json([
            'delivery_charge' => (float) (StoreSetting::whereKey('delivery_charge')->value('value') ?? 0),
        ]);
    }
}
