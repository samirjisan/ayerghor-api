<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'delivery_charge' => (float) StoreSetting::whereKey('delivery_charge')->value('value'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'delivery_charge' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        StoreSetting::updateOrCreate(
            ['key' => 'delivery_charge'],
            ['value' => number_format((float) $data['delivery_charge'], 2, '.', '')]
        );

        return $this->show();
    }
}
