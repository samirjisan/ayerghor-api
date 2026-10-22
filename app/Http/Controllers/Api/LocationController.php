<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function districts(): JsonResponse
    {
        return response()->json(
            District::orderBy('name')->get(['id', 'name', 'bn_name'])
        );
    }

    public function thanas(District $district): JsonResponse
    {
        return response()->json(
            $district->thanas()->orderBy('name')->get(['id', 'district_id', 'name', 'bn_name'])
        );
    }
}
