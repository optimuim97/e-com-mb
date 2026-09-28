<?php

namespace Modules\Cart\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Modules\Cart\Models\Cart;

class AbandonedCartController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'abandoned_carts.view' => ['stats', 'index'],
        ]);
    }

    /**
     * GET /admin/abandoned-carts/stats
     */
    public function stats(): JsonResponse
    {
        $total     = Cart::whereNotNull('abandoned_at')->count();
        $pending1  = Cart::whereNotNull('abandoned_at')->whereNull('reminder_1_sent_at')->count();
        $sent1     = Cart::whereNotNull('reminder_1_sent_at')->count();
        $sent3     = Cart::whereNotNull('reminder_3_sent_at')->count();

        // Estimated revenue at risk: sum of subtotals of abandoned carts
        $carts = Cart::with('items.product', 'items.variant')
            ->whereNotNull('abandoned_at')
            ->get();

        $revenueAtRisk = $carts->sum(fn($c) => $c->subtotal);

        return response()->json([
            'total_abandoned'   => $total,
            'pending_j1'        => $pending1,
            'reminder_1_sent'   => $sent1,
            'reminder_3_sent'   => $sent3,
            'revenue_at_risk'   => round($revenueAtRisk, 0),
        ]);
    }

    /**
     * GET /admin/abandoned-carts
     * Paginated list of abandoned carts for admin visibility.
     */
    public function index(Request $request): JsonResponse
    {
        $carts = Cart::with(['user', 'items.product'])
            ->whereNotNull('abandoned_at')
            ->when($request->filled('status'), function ($q) use ($request) {
                match ($request->status) {
                    'no_reminder' => $q->whereNull('reminder_1_sent_at'),
                    'j1_sent'     => $q->whereNotNull('reminder_1_sent_at')->whereNull('reminder_3_sent_at'),
                    'j3_sent'     => $q->whereNotNull('reminder_3_sent_at'),
                    default       => null,
                };
            })
            ->latest('abandoned_at')
            ->paginate(20);

        return response()->json([
            'data' => $carts->map(fn($cart) => [
                'id'                 => $cart->id,
                'user_name'          => $cart->user?->name ?? '—',
                'user_email'         => $cart->user?->email ?? '—',
                'items_count'        => $cart->items->count(),
                'subtotal'           => $cart->subtotal,
                'abandoned_at'       => $cart->abandoned_at?->format('d/m/Y H:i'),
                'reminder_1_sent_at' => $cart->reminder_1_sent_at?->format('d/m/Y H:i'),
                'reminder_3_sent_at' => $cart->reminder_3_sent_at?->format('d/m/Y H:i'),
            ]),
            'meta' => [
                'current_page' => $carts->currentPage(),
                'last_page'    => $carts->lastPage(),
                'total'        => $carts->total(),
            ],
        ]);
    }
}
