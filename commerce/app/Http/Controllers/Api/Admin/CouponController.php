<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Coupon::latest()->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'           => 'nullable|string|unique:coupons',
            'type'           => 'required|in:percentage,fixed,free_shipping',
            'value'          => 'required|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'is_active'      => 'boolean',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date|after:starts_at',
        ]);
        $data['code'] = strtoupper($data['code'] ?? Str::random(8));
        return response()->json(Coupon::create($data), 201);
    }

    public function show(Coupon $coupon): JsonResponse { return response()->json($coupon); }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'type'           => 'sometimes|in:percentage,fixed,free_shipping',
            'value'          => 'sometimes|numeric|min:0',
            'minimum_amount' => 'nullable|numeric|min:0',
            'usage_limit'    => 'nullable|integer|min:1',
            'is_active'      => 'boolean',
            'starts_at'      => 'nullable|date',
            'expires_at'     => 'nullable|date',
        ]);
        $coupon->update($data);
        return response()->json($coupon);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json(['message' => 'Coupon supprimé']);
    }
}
