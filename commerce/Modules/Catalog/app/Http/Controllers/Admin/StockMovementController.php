<?php

namespace Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Catalog\Http\Resources\StockMovementResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;

/**
 * Historique des entrées et sorties de stock.
 *
 * Répond à « où sont passés les articles ? » : chaque ligne dit combien, quand,
 * pourquoi, et sur quelle pièce. L'écran sert aussi à saisir une correction
 * après comptage — une correction qui laisse elle-même une trace.
 */
class StockMovementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'stock.view'   => ['index', 'summary'],
            // Corriger un stock engage l'inventaire : ce n'est pas le même
            // niveau de responsabilité que le consulter.
            'stock.adjust' => ['adjust'],
        ]);
    }

    public function index(Request $request): ResourceCollection
    {
        $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'type'       => 'nullable|string|in:' . implode(',', StockMovement::TYPES),
            'direction'  => 'nullable|in:in,out',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'search'     => 'nullable|string|max:120',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $query = StockMovement::query()
            ->with(['product:id,name,sku', 'variant:id,sku', 'user:id,name'])
            ->latest('id');

        $query->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')));
        $query->when($request->filled('variant_id'), fn ($q) => $q->where('variant_id', $request->integer('variant_id')));
        $query->when($request->filled('type'),       fn ($q) => $q->where('type', $request->string('type')));

        $query->when($request->input('direction') === 'in',  fn ($q) => $q->entrees());
        $query->when($request->input('direction') === 'out', fn ($q) => $q->sorties());

        $query->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')));
        $query->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        // Recherche par produit : c'est le réflexe naturel devant un écart —
        // on part du pot qui manque, pas d'un identifiant.
        $query->when($request->filled('search'), function ($q) use ($request) {
            $terme = '%' . $request->string('search') . '%';
            $q->whereHas('product', fn ($p) => $p->where('name', 'like', $terme)->orWhere('sku', 'like', $terme));
        });

        return StockMovementResource::collection(
            $query->paginate($request->integer('per_page') ?: 25)->withQueryString()
        );
    }

    /**
     * Totaux de la période filtrée : entrées, sorties, variation nette.
     * Affichés en tête d'écran pour situer les lignes qu'on lit en dessous.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = StockMovement::query();

        $query->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')));
        $query->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')));
        $query->when($request->filled('to'),   fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        $entrees = (int) (clone $query)->entrees()->sum('quantity');
        $sorties = (int) (clone $query)->sorties()->sum('quantity');

        return response()->json([
            'entrees'   => $entrees,
            'sorties'   => abs($sorties),
            'net'       => $entrees + $sorties,
            'mouvements' => (int) (clone $query)->count(),
        ]);
    }

    /**
     * Correction manuelle après comptage : on saisit le stock constaté, le
     * registre calcule l'écart et l'enregistre.
     */
    public function adjust(Request $request, StockLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'counted'    => 'required|integer|min:0',
            'reason'     => 'required|string|max:255',
            'type'       => 'nullable|string|in:' . implode(',', [
                StockMovement::TYPE_ADJUSTMENT,
                StockMovement::TYPE_LOSS,
                StockMovement::TYPE_RETURN,
            ]),
        ]);

        $produit     = Product::findOrFail($data['product_id']);
        $declinaison = isset($data['variant_id']) ? ProductVariant::find($data['variant_id']) : null;

        if ($declinaison && $declinaison->product_id !== $produit->id) {
            return response()->json([
                'message' => 'Cette déclinaison n\'appartient pas au produit indiqué.',
            ], 422);
        }

        if (! $produit->track_stock) {
            return response()->json([
                'message' => 'Ce produit ne suit pas son stock : il n\'y a rien à corriger.',
            ], 422);
        }

        $mouvement = $ledger->adjustTo($produit, $declinaison, (int) $data['counted'], [
            'type'   => $data['type'] ?? StockMovement::TYPE_ADJUSTMENT,
            'reason' => $data['reason'],
        ]);

        if (! $mouvement) {
            return response()->json([
                'message' => 'Le stock était déjà à cette valeur : aucun mouvement enregistré.',
            ], 200);
        }

        return response()->json([
            'message'   => 'Stock corrigé.',
            'movement'  => new StockMovementResource($mouvement->load(['product:id,name,sku', 'variant:id,sku', 'user:id,name'])),
        ], 201);
    }
}
