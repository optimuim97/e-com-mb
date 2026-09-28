<?php

namespace Modules\Promo\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Catalog\Support\HomeStatsCache;
use Modules\Promo\Models\Promotion;
use Modules\Promo\Services\PromotionResolver;

class PromotionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('promotions');
    }

    public function __construct(private readonly PromotionResolver $resolver) {}

    public function index(Request $request): JsonResponse
    {
        $query = Promotion::query()
            ->with('products:id,name,price')
            ->withCount('products');

        if ($request->boolean('active_only')) {
            $query->activeNow();
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }
        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json(
            $query->orderByDesc('priority')->orderByDesc('id')->paginate(30)
        );
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($promotion->load('products:id,name,price'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $promotion = Promotion::create($data);
        $this->syncProducts($promotion, $data);

        return response()->json($promotion->load('products:id,name,price'), 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $this->validated($request, $promotion);

        $promotion->update($data);
        $this->syncProducts($promotion, $data);

        return response()->json($promotion->fresh()->load('products:id,name,price'));
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $promotion->delete();
        $this->resolver->forget();

        return response()->json(['message' => 'Promotion supprimée.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Promotion $existing = null): array
    {
        $required = $existing ? 'sometimes' : 'required';

        $data = $request->validate([
            'name'                => "$required|string|max:150",
            'description'         => 'nullable|string|max:1000',
            'scope'               => "$required|in:" . implode(',', Promotion::SCOPES),
            'type'                => 'nullable|in:' . implode(',', Promotion::TYPES),
            'discount_type'       => "$required|in:" . implode(',', Promotion::DISCOUNT_TYPES),
            'value'               => "$required|numeric|min:0",
            'max_discount_amount' => 'nullable|numeric|min:0',
            'banner_label'        => 'nullable|string|max:40',
            'starts_at'           => 'nullable|date',
            'ends_at'             => 'nullable|date|after:starts_at',
            'is_active'           => 'boolean',
            'priority'            => 'nullable|integer|min:0',
            // Articles rattachés. Chacun peut porter sa propre remise :
            // laissée vide, il suit celle de l'opération.
            'products'                 => 'array',
            'products.*.id'            => 'required|integer|exists:products,id',
            'products.*.discount_type' => 'nullable|in:' . implode(',', Promotion::DISCOUNT_TYPES),
            'products.*.value'         => 'nullable|numeric|min:0',
            // Forme simplifiée, sans remise dérogatoire.
            'product_ids'         => 'array',
            'product_ids.*'       => 'integer|exists:products,id',
        ]);

        // Une remise dérogatoire en pourcentage obéit à la même borne.
        foreach ($data['products'] ?? [] as $i => $row) {
            $rowType = $row['discount_type'] ?? $data['discount_type'] ?? $existing?->discount_type;
            if ($rowType === Promotion::DISCOUNT_PERCENT
                && isset($row['value']) && (float) $row['value'] > 100) {
                abort(422, "Article #{$i} : une remise en pourcentage ne peut pas dépasser 100 %.");
            }
        }

        $discountType = $data['discount_type'] ?? $existing?->discount_type;
        $value        = $data['value'] ?? $existing?->value;

        // Une remise en pourcentage au-delà de 100 rendrait le prix négatif ;
        // le modèle plafonne à zéro, mais autant refuser la saisie.
        if ($discountType === Promotion::DISCOUNT_PERCENT && $value !== null && (float) $value > 100) {
            abort(422, 'Une remise en pourcentage ne peut pas dépasser 100 %.');
        }

        // Un plafond sur une remise déjà exprimée en montant fixe n'a pas de
        // sens : il ne pourrait que répéter ou contredire la valeur saisie.
        if ($discountType === Promotion::DISCOUNT_FIXED && ! empty($data['max_discount_amount'])) {
            abort(422, 'Le plafond ne s’applique qu’aux remises en pourcentage.');
        }

        // Une promotion ciblée sans produit ne s'appliquerait à rien : mieux
        // vaut le dire à la saisie que de la voir rester sans effet.
        $scope = $data['scope'] ?? $existing?->scope;
        if ($scope === Promotion::SCOPE_PRODUCT) {
            $ids = collect($data['products'] ?? [])->pluck('id')->all()
                ?: ($data['product_ids'] ?? ($existing?->products->pluck('id')->all() ?? []));
            if (empty($ids)) {
                abort(422, 'Une promotion ciblée doit porter sur au moins un article.');
            }
        }

        return $data;
    }

    /**
     * Rattache les articles, remise dérogatoire comprise.
     *
     * `sync` accepte un tableau id => attributs de pivot : c'est ainsi que la
     * remise propre à chaque article est enregistrée.
     */
    private function syncProducts(Promotion $promotion, array $data): void
    {
        if ($promotion->scope === Promotion::SCOPE_PRODUCT && isset($data['products'])) {
            $promotion->products()->sync(
                collect($data['products'])->mapWithKeys(fn (array $row) => [
                    $row['id'] => [
                        'discount_type' => $row['discount_type'] ?? null,
                        'value'         => $row['value'] ?? null,
                    ],
                ])->all()
            );
        } elseif ($promotion->scope === Promotion::SCOPE_PRODUCT && isset($data['product_ids'])) {
            $promotion->products()->sync($data['product_ids']);
        } elseif ($promotion->scope === Promotion::SCOPE_GLOBAL) {
            // Une promotion repassée en portée globale ne doit pas conserver de
            // rattachements devenus trompeurs dans l'interface.
            $promotion->products()->detach();
        }

        $this->resolver->forget();

        // Le rattachement passe par le pivot : aucun événement de modèle n'est
        // émis, l'accueil resterait donc sur sa version en cache alors que
        // l'opération vient de changer d'articles.
        HomeStatsCache::forget();
    }
}
