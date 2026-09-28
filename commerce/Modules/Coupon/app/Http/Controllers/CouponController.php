<?php

namespace Modules\Coupon\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Coupon\Models\Coupon;
use Modules\Coupon\Services\CouponService;

class CouponController extends Controller implements HasMiddleware
{
    // checkCode (validation publique du code) reste hors périmètre permission.
    public static function middleware(): array
    {
        return ResourcePermissions::crud('coupons');
    }

    public function __construct(private readonly CouponService $couponService) {}

    public function index(): JsonResponse
    {
        return response()->json($this->couponService->list());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'          => 'nullable|string|unique:coupons',
            // Renseigné : le code est réservé à cette cliente.
            'customer_id'   => 'nullable|integer|exists:users,id',
            'type'          => 'required|in:percentage,fixed,free_shipping',
            'value'         => 'required|numeric|min:0',
            'minimum_amount'=> 'nullable|numeric|min:0',
            'usage_limit'   => 'nullable|integer|min:1',
            'is_active'     => 'boolean',
            'starts_at'     => 'nullable|date',
            'expires_at'    => 'nullable|date|after:starts_at',
            // Renseigné : le code ne remise que ces articles. Vide ou absent :
            // il porte sur tout le panier, comme auparavant.
            'product_ids'   => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        return response()->json($this->couponService->create($data), 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json($coupon->load('products:id,name'));
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'customer_id'   => 'nullable|integer|exists:users,id',
            'type'          => 'sometimes|in:percentage,fixed,free_shipping',
            'value'         => 'sometimes|numeric|min:0',
            'minimum_amount'=> 'nullable|numeric|min:0',
            'usage_limit'   => 'nullable|integer|min:1',
            'is_active'     => 'boolean',
            'starts_at'     => 'nullable|date',
            'expires_at'    => 'nullable|date',
            'product_ids'   => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        return response()->json($this->couponService->update($coupon, $data));
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->couponService->delete($coupon);
        return response()->json(['message' => 'Coupon supprimé']);
    }

    public function checkCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'               => 'required|string',
            // Lignes facultatives : fournies, elles font chiffrer la remise
            // côté serveur au lieu de laisser le front la deviner.
            'items'              => 'nullable|array',
            'items.*.product_id' => 'required_with:items|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'   => 'required_with:items|integer|min:1',
        ]);

        try {
            return response()->json($this->couponService->check(
                $data['code'],
                $request->user()?->id,
                $data['items'] ?? [],
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
