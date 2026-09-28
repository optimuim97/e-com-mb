<?php

namespace Modules\Wishlist\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Wishlist\Http\Resources\WishlistItemResource;
use Modules\Wishlist\Models\Wishlist;

class WishlistController extends Controller
{
    /** GET /wishlist — liste complète de la wishlist de l'utilisateur */
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with(['product.images'])
            ->latest()
            ->get();

        $items = $items->map(function ($item) {
            return !is_null($item->product) ? $item : [];
        });

        $items = $items->filter();

        return response()->json([
            'items'        => WishlistItemResource::collection($items),
            'product_ids'  => $items->pluck('product_id'),
            'count'        => $items->count(),
        ]);
    }

    /** POST /wishlist/toggle — ajoute ou retire un produit */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|integer|exists:products,id']);

        $userId    = $request->user()->id;
        $productId = $request->integer('product_id');

        $existing = Wishlist::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
        } else {
            Wishlist::create(['user_id' => $userId, 'product_id' => $productId]);
            $wishlisted = true;
        }

        $count = Wishlist::where('user_id', $userId)->count();

        return response()->json([
            'wishlisted'  => $wishlisted,
            'product_id'  => $productId,
            'count'       => $count,
        ]);
    }

    /** DELETE /wishlist/{product_id} — retire un produit directement */
    public function remove(Request $request, int $productId): JsonResponse
    {
        Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        $count = Wishlist::where('user_id', $request->user()->id)->count();

        return response()->json(['product_id' => $productId, 'count' => $count]);
    }
}
