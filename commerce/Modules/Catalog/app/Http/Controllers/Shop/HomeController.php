<?php

namespace Modules\Catalog\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Services\OnlineAvailability;
use Modules\Catalog\Support\HomeStatsCache;
use Modules\Promo\Models\Promotion;
use Modules\Review\Models\Review;

class HomeController extends Controller
{
    /**
     * GET /home/stats
     * Données dynamiques pour la page d'accueil.
     *
     * Mises en cache 5 min : ces données ne sont pas propres à l'utilisateur
     * et représentaient ~9 requêtes lourdes à chaque chargement. Le cache
     * accélère fortement les chargements suivants (le 1er reste calculé).
     */
    public function stats(Request $request): JsonResponse
    {
        // Les clés, leur durée et ce qui les périme vivent dans HomeStatsCache :
        // le cache n'était vidé nulle part, et une promotion créée en
        // administration attendait cinq minutes avant de s'afficher.
        $payload = HomeStatsCache::remember(
            (bool) $request->user()?->can('finance.view'),
            fn () => $this->computeStats(),
        );

        return response()->json($this->withLiveAvailability($payload));
    }

    /**
     * Remplace, dans la charge utile mise en cache, la disponibilité des
     * articles par celle de l'instant.
     *
     * Le cache garde ce qui bouge peu — prix, images, avis. La disponibilité,
     * elle, change à chaque vente : figée cinq minutes, la page d'accueil
     * afficherait « plus que 2 » sur des pots partis à 9 h, et on retomberait
     * sur le refus au paiement que tout ce dispositif existe pour éviter.
     * Invalider le cache à chaque vente reviendrait à ne plus en avoir.
     */
    private function withLiveAvailability(array $payload): array
    {
        $blocs = [
            'flash_sales'    => $payload['flash_sales'] ?? [],
            'new_collection' => $payload['new_collection'] ?? [],
            'campaign'       => $payload['promo_campaign']['products'] ?? [],
            'best_seller'    => isset($payload['best_seller']) ? [$payload['best_seller']] : [],
        ];

        $ids = collect($blocs)->flatten(1)->pluck('id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $payload;
        }

        $products = Product::with('variants:id,product_id,stock')
            ->whereIn('id', $ids)
            ->get(['id', 'stock', 'track_stock', 'has_variants'])
            ->keyBy('id');

        $availability = app(OnlineAvailability::class);

        $rafraichir = function (array $item) use ($products, $availability): array {
            $product = $products->get($item['id'] ?? null);

            // Article retiré de la base depuis la mise en cache : on le laisse
            // tel quel plutôt que d'inventer un état.
            if (! $product) {
                return $item;
            }

            $etat = $availability->for($product);

            return array_merge($item, [
                'stock'          => $etat['sellable'],
                'sellable_stock' => $etat['sellable'],
                'availability'   => $etat['status'],
            ]);
        };

        foreach (['flash_sales', 'new_collection'] as $cle) {
            if (isset($payload[$cle])) {
                $payload[$cle] = array_map($rafraichir, $payload[$cle]);
            }
        }

        if (isset($payload['best_seller'])) {
            $payload['best_seller'] = $rafraichir($payload['best_seller']);
        }

        if (isset($payload['promo_campaign']['products'])) {
            $payload['promo_campaign']['products'] = array_map($rafraichir, $payload['promo_campaign']['products']);
        }

        return $payload;
    }

    private function computeStats(): array
    {
        // ── Best-seller : produit le plus commandé ───────────────────────
        $bestSellerId = OrderItem::selectRaw('product_id, SUM(quantity) as total_sold')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->value('product_id');

        $bestSeller = $bestSellerId
            ? Product::with('images', 'category')
                ->where('is_active', true)
                ->find($bestSellerId)
            : Product::with('images', 'category')
                ->where('is_active', true)
                ->where('is_featured', true)
                ->latest()
                ->first();

        // ── Reviews aggregates ───────────────────────────────────────────
        $reviewsCount = Review::where('status', 'approved')->count();
        $ratingAvg    = (float) (Review::where('status', 'approved')->avg('rating') ?? 0);

        // ── Clients count ────────────────────────────────────────────────
        $clientsCount = User::query()
            ->whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['admin', 'staff']))
            ->count();

        // ── Featured review (top of the hero) ────────────────────────────
        $featuredReview = Review::with('user', 'product')
            ->where('status', 'approved')
            ->where('rating', '>=', 4)
            ->orderByDesc('helpful_count')
            ->orderByDesc('created_at')
            ->first();

        // ── Testimonials (6 derniers approuvés, 4+ étoiles) ──────────────
        $testimonials = Review::with('user', 'product')
            ->where('status', 'approved')
            ->where('rating', '>=', 4)
            ->whereNotNull('comment')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn($r) => [
                'id'       => $r->id,
                'name'     => $r->user?->name ?? 'Cliente',
                'location' => 'Côte d\'Ivoire',
                'quote'    => $r->comment,
                'rating'   => $r->rating,
                'product'  => $r->product?->name,
                'created'  => $r->created_at->diffForHumans(),
            ]);

        // ── Flash sale products (actifs maintenant) ──────────────────────
        $now = now();
        $flashSales = Product::with('images', 'category')
            ->where('is_active', true)
            ->where('is_flash_sale', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('flash_starts_at')->orWhere('flash_starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('flash_ends_at')->orWhere('flash_ends_at', '>=', $now);
            })
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        // ── Nouveautés ───────────────────────────────────────────────────
        $newCollection = Product::with('images', 'category')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('is_new', true)
                  ->orWhere('created_at', '>=', now()->subDays(30));
            })
            ->latest()
            ->limit(8)
            ->get();

        return [
            'reviews_count'    => $reviewsCount,
            'rating_avg'       => round($ratingAvg, 1),
            'clients_count'    => $clientsCount,
            'featured_review'  => $featuredReview ? [
                'id'       => $featuredReview->id,
                'name'     => $featuredReview->user?->name ?? 'Cliente',
                'initial'  => strtoupper(mb_substr($featuredReview->user?->name ?? 'C', 0, 1)),
                'rating'   => $featuredReview->rating,
                'quote'    => $featuredReview->comment ?? $featuredReview->title,
                'product'  => $featuredReview->product?->name,
                'location' => 'Abidjan',
            ] : null,
            'testimonials'     => $testimonials,
            'flash_sales'      => ProductResource::collection($flashSales)->resolve(),
            'new_collection'   => ProductResource::collection($newCollection)->resolve(),
            'best_seller'      => $bestSeller ? (new ProductResource($bestSeller))->resolve() : null,
            'promo_campaign'   => $this->promoCampaign(),
        ];
    }

    /**
     * Opération en cours mise en avant sur l'accueil.
     *
     * Une seule : afficher deux bannières concurrentes brouille le message et
     * les promotions ne se cumulent pas de toute façon. On retient la plus
     * prioritaire, celle-là même que le résolveur appliquera au panier.
     */
    private function promoCampaign(): ?array
    {
        $campaign = Promotion::query()
            ->activeNow()
            ->with(['products' => fn ($q) => $q->where('is_active', true)->with('images', 'category')])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        if (! $campaign) {
            return null;
        }

        // Opération ciblée dont tous les articles sont retirés de la vente :
        // elle tourne encore en base mais n'a plus rien à annoncer.
        if ($campaign->scope === Promotion::SCOPE_PRODUCT && $campaign->products->isEmpty()) {
            return null;
        }

        return [
            'id'             => $campaign->id,
            'name'           => $campaign->name,
            'description'    => $campaign->description,
            'type'           => $campaign->type,
            'scope'          => $campaign->scope,
            // Le libellé saisi en administration, et lui seul. `badge()` se
            // rabat sinon sur le taux de l'opération : la bannière affichait
            // « −25 % » au-dessus d'un « jusqu'à −15 % » calculé sur les prix
            // réels, l'article portant sa propre remise. Sans libellé, le front
            // affiche la nature de l'opération, traduite.
            'banner_label'   => $campaign->banner_label,
            'discount_type'  => $campaign->discount_type,
            'value'          => $campaign->value,
            // Remise la plus forte de l'opération : c'est le « jusqu'à −X % »
            // de la bannière. Nulle quand la remise est un montant fixe, qui ne
            // se traduit pas en pourcentage sans choisir un article.
            'max_percent'    => $this->campaignMaxPercent($campaign),
            'products_count' => $campaign->products->count(),
            'starts_at'      => $campaign->starts_at,
            'ends_at'        => $campaign->ends_at,
            // Aperçu : de quoi montrer trois articles sous la bannière sans
            // forcer un aller-retour vers le catalogue.
            'products'       => ProductResource::collection($campaign->products->take(3))->resolve(),
        ];
    }

    /** Remise maximale de l'opération, en pourcentage entier. */
    private function campaignMaxPercent(Promotion $campaign): ?int
    {
        if ($campaign->scope === Promotion::SCOPE_GLOBAL) {
            return $campaign->discount_type === Promotion::DISCOUNT_PERCENT
                ? (int) round((float) $campaign->value)
                : null;
        }

        // Portée « articles » : chacun peut porter sa propre remise, le taux
        // annoncé sur l'opération ne suffit donc pas.
        $percents = $campaign->products
            ->filter(fn (Product $product) => (float) $product->price > 0)
            ->map(fn (Product $product) => (
                1 - $campaign->priceForProduct($product) / (float) $product->price
            ) * 100)
            ->filter(fn (float $percent) => $percent > 0);

        return $percents->isEmpty() ? null : (int) round($percents->max());
    }
}
