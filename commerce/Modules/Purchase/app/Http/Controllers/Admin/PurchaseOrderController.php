<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\ProductVariant;
use Modules\Purchase\Http\Resources\PurchaseOrderResource;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderItem;
use Modules\Purchase\Services\PurchaseReceiver;

/**
 * Bons d'achat : saisie, puis réception.
 *
 * Un bon reste modifiable tant qu'il est en préparation. Une fois réceptionné,
 * il est figé : ses coûts ont déjà servi à calculer le stock et le coût moyen,
 * les rejouer donnerait deux valeurs différentes pour la même entrée.
 */
class PurchaseOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('purchases', extra: [
            // Réceptionner engage le stock : c'est un geste d'écriture, pas
            // une simple mise à jour de brouillon.
            'purchases.edit' => ['receive', 'cancel'],
        ]);
    }

    public function index(Request $request): ResourceCollection
    {
        $query = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->latest('id');

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));
        $query->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')));
        $query->when($request->filled('search'), function ($q) use ($request) {
            $terme = '%' . $request->string('search') . '%';
            $q->where(fn ($p) => $p->where('number', 'like', $terme)
                                   ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $terme)));
        });

        return PurchaseOrderResource::collection(
            $query->paginate($request->integer('per_page') ?: 25)->withQueryString()
        );
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource(
            $purchaseOrder->load(['supplier', 'items.product:id,name,sku,average_cost', 'items.variant:id,sku'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $order = DB::transaction(function () use ($data) {
            $order = PurchaseOrder::create([
                'number'        => PurchaseOrder::generateNumber(),
                'supplier_id'   => $data['supplier_id'] ?? null,
                'status'        => PurchaseOrder::STATUS_DRAFT,
                'currency'      => 'XOF',
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'other_costs'   => $data['other_costs']   ?? 0,
                'ordered_at'    => $data['ordered_at']    ?? now()->toDateString(),
                'notes'         => $data['notes']         ?? null,
            ]);

            $this->syncItems($order, $data['items']);

            return $order;
        });

        return response()->json(
            new PurchaseOrderResource($order->load(['supplier', 'items.product:id,name,sku', 'items.variant:id,sku'])),
            201
        );
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! $purchaseOrder->isEditable()) {
            return response()->json([
                'message' => "Ce bon d'achat est {$purchaseOrder->status_label} : il n'est plus modifiable.",
            ], 422);
        }

        $data = $request->validate($this->rules());

        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update([
                'supplier_id'   => $data['supplier_id']   ?? null,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'other_costs'   => $data['other_costs']   ?? 0,
                'ordered_at'    => $data['ordered_at']    ?? $purchaseOrder->ordered_at,
                'notes'         => $data['notes']         ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $data['items']);
        });

        return response()->json(
            new PurchaseOrderResource(
                $purchaseOrder->refresh()->load(['supplier', 'items.product:id,name,sku', 'items.variant:id,sku'])
            )
        );
    }

    /**
     * Réception : entrée en stock, prix de revient et coût moyen pondéré.
     */
    public function receive(PurchaseOrder $purchaseOrder, PurchaseReceiver $receiver): JsonResponse
    {
        try {
            $purchaseOrder = $receiver->receive($purchaseOrder);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Bon d'achat réceptionné : le stock et les coûts sont à jour.",
            'order'   => new PurchaseOrderResource($purchaseOrder),
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return response()->json([
                'message' => "Ce bon est déjà réceptionné : le stock est entré. Corrigez-le par un mouvement d'inventaire.",
            ], 422);
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

        return response()->json(['message' => "Bon d'achat annulé."]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status === PurchaseOrder::STATUS_RECEIVED) {
            return response()->json([
                'message' => "Un bon réceptionné ne se supprime pas : il justifie du stock déjà entré.",
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json(['message' => "Bon d'achat supprimé."]);
    }

    // ── Interne ────────────────────────────────────────────────────────────

    /**
     * (Re)crée les lignes et recalcule les totaux du bon.
     *
     * Les totaux sont recalculés côté serveur : accepter ceux du navigateur
     * laisserait passer un bon dont le total ne correspond pas à ses lignes.
     *
     * @param  array<int, array<string, mixed>>  $lignes
     */
    private function syncItems(PurchaseOrder $order, array $lignes): void
    {
        $valeurLignes = 0.0;

        foreach ($lignes as $ligne) {
            // Une déclinaison doit appartenir au produit de sa ligne, sinon le
            // stock entrerait sur un autre article que celui saisi.
            $variantId = $ligne['variant_id'] ?? null;
            if ($variantId !== null) {
                $declinaison = ProductVariant::find($variantId);
                if (! $declinaison || $declinaison->product_id !== (int) $ligne['product_id']) {
                    $variantId = null;
                }
            }

            $quantite = (int) $ligne['quantity'];
            $cout     = (float) $ligne['unit_cost'];
            $total    = round($quantite * $cout, 2);

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'product_id'        => $ligne['product_id'],
                'variant_id'        => $variantId,
                'quantity'          => $quantite,
                'unit_cost'         => $cout,
                'total_cost'        => $total,
            ]);

            $valeurLignes += $total;
        }

        $order->forceFill([
            'items_total' => round($valeurLignes, 2),
            'total'       => round($valeurLignes + $order->extraCosts(), 2),
        ])->save();
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'supplier_id'         => 'nullable|integer|exists:suppliers,id',
            'shipping_cost'       => 'nullable|numeric|min:0',
            'other_costs'         => 'nullable|numeric|min:0',
            'ordered_at'          => 'nullable|date',
            'notes'               => 'nullable|string|max:2000',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer|exists:products,id',
            'items.*.variant_id'  => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_cost'   => 'required|numeric|min:0',
        ];
    }
}
