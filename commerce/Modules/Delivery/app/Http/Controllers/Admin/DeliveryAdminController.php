<?php

namespace Modules\Delivery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Delivery\Enums\DeliveryStatus;
use Modules\Delivery\Models\Courier;
use Modules\Delivery\Models\Delivery;

class DeliveryAdminController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'deliveries.view' => ['index', 'show', 'stats', 'couriers'],
            'deliveries.edit' => ['store', 'assign', 'updateStatus'],
        ]);
    }

    /**
     * GET /admin/deliveries
     * Liste paginée avec filtres statut / livreur / recherche
     */
    public function index(Request $request): JsonResponse
    {
        $deliveries = Delivery::with(['customer', 'courier', 'payments'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('courier_id'), fn($q) => $q->where('courier_id', $request->courier_id))
            ->when($request->filled('search'), fn($q) =>
                $q->where('order_number', 'like', '%' . $request->search . '%')
            )
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $deliveries->map(fn($d) => $this->formatDelivery($d)),
            'meta' => [
                'total'       => $deliveries->total(),
                'per_page'    => $deliveries->perPage(),
                'current_page'=> $deliveries->currentPage(),
                'last_page'   => $deliveries->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/deliveries/{id}
     */
    public function show(int $id): JsonResponse
    {
        $delivery = Delivery::with(['customer', 'courier', 'payments', 'assignments.courier'])
            ->findOrFail($id);

        return response()->json(['data' => $this->formatDelivery($delivery, detailed: true)]);
    }

    /**
     * POST /admin/deliveries
     * Créer une livraison manuellement pour une commande
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number'   => 'required|string|max:50',
            'courier_id'     => 'nullable|integer|exists:couriers,id',
            'payment_flow'   => 'required|in:COD,PREPAID,MIXED',
            'product_amount' => 'required|integer|min:0',
            'delivery_fee'   => 'nullable|integer|min:0',
            'notes'          => 'nullable|string|max:500',
        ]);

        $delivery = Delivery::create([
            'order_number'   => $validated['order_number'],
            'courier_id'     => $validated['courier_id'] ?? null,
            'status'         => DeliveryStatus::PENDING,
            'payment_flow'   => $validated['payment_flow'],
            'product_amount' => $validated['product_amount'],
            'delivery_fee'   => $validated['delivery_fee'] ?? 0,
            'total_amount'   => $validated['product_amount'] + ($validated['delivery_fee'] ?? 0),
            'notes'          => $validated['notes'] ?? null,
            'assigned_at'    => $validated['courier_id'] ? now() : null,
        ]);

        return response()->json(['data' => $this->formatDelivery($delivery->fresh(['customer', 'courier']))], 201);
    }

    /**
     * PATCH /admin/deliveries/{id}/assign
     * Assigner ou changer le livreur
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'courier_id' => 'required|integer|exists:couriers,id',
        ]);

        $delivery = Delivery::findOrFail($id);
        $delivery->update([
            'courier_id'  => $request->courier_id,
            'assigned_at' => now(),
            'status'      => DeliveryStatus::PENDING,
        ]);

        return response()->json([
            'message' => 'Livreur assigné avec succès.',
            'data'    => $this->formatDelivery($delivery->fresh(['customer', 'courier'])),
        ]);
    }

    /**
     * PATCH /admin/deliveries/{id}/status
     * Mettre à jour le statut (admin override)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status'         => 'required|in:' . implode(',', array_column(DeliveryStatus::cases(), 'value')),
            'failure_reason' => 'nullable|string|max:500',
        ]);

        $delivery = Delivery::findOrFail($id);
        $delivery->update([
            'status'         => DeliveryStatus::from($request->status),
            'failure_reason' => $request->failure_reason ?? null,
            'delivered_at'   => $request->status === 'DELIVERED' ? now() : $delivery->delivered_at,
        ]);

        return response()->json([
            'message' => 'Statut mis à jour.',
            'data'    => $this->formatDelivery($delivery->fresh(['customer', 'courier'])),
        ]);
    }

    /**
     * GET /admin/couriers
     * Liste des livreurs (pour les selects)
     */
    public function couriers(): JsonResponse
    {
        $couriers = Courier::select('id', 'first_name', 'last_name', 'phone', 'status')
            ->orderBy('first_name')
            ->get()
            ->map(fn($c) => [
                'id'        => $c->id,
                'full_name' => $c->full_name,
                'phone'     => $c->phone,
                'status'    => $c->status->value,
            ]);

        return response()->json(['data' => $couriers]);
    }

    /**
     * GET /admin/deliveries/stats
     * Statistiques rapides pour le dashboard
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'pending'     => Delivery::where('status', DeliveryStatus::PENDING)->count(),
            'in_progress' => Delivery::where('status', DeliveryStatus::IN_PROGRESS)->count(),
            'delivered'   => Delivery::where('status', DeliveryStatus::DELIVERED)->count(),
            'failed'      => Delivery::where('status', DeliveryStatus::FAILED)->count(),
            'unassigned'  => Delivery::whereNull('courier_id')->count(),
        ]);
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function formatDelivery(Delivery $d, bool $detailed = false): array
    {
        $base = [
            'id'             => $d->id,
            'order_number'   => $d->order_number,
            'status'         => $d->status->value,
            'payment_flow'   => $d->payment_flow?->value,
            'product_amount' => $d->product_amount,
            'delivery_fee'   => $d->delivery_fee,
            'total_amount'   => $d->total_amount,
            'notes'          => $d->notes,
            'failure_reason' => $d->failure_reason,
            'assigned_at'    => $d->assigned_at?->toIso8601String(),
            'delivered_at'   => $d->delivered_at?->toIso8601String(),
            'created_at'     => $d->created_at->toIso8601String(),
            'customer'       => $d->customer ? [
                'id'    => $d->customer->id,
                'name'  => $d->customer->name ?? 'N/A',
                'phone' => $d->customer->phone ?? null,
            ] : null,
            'courier' => $d->courier ? [
                'id'        => $d->courier->id,
                'full_name' => $d->courier->full_name,
                'phone'     => $d->courier->phone,
            ] : null,
        ];

        if ($detailed) {
            $base['payments']    = $d->payments->toArray();
            $base['assignments'] = $d->assignments->map(fn($a) => [
                'id'           => $a->id,
                'courier_name' => $a->courier?->full_name,
                'created_at'   => $a->created_at->toIso8601String(),
            ])->toArray();
        }

        return $base;
    }
}
