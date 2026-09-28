<?php

namespace Modules\Coupon\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Coupon\Models\Coupon;
use Modules\Coupon\Support\CouponDiscount;
use Modules\Promo\Support\SellingPrice;

class CouponService
{
    public function list(): LengthAwarePaginator
    {
        // Les articles ciblés partent avec la liste : l'écran doit pouvoir dire
        // « ce code ne vaut que sur X » sans une requête par ligne.
        return Coupon::with('products:id,name')->latest()->paginate(20);
    }

    public function create(array $data): Coupon
    {
        $data['code'] = strtoupper($data['code'] ?? Str::random(8));

        $coupon = Coupon::create(Arr::except($data, ['product_ids']));
        $this->syncProducts($coupon, $data);

        return $coupon->load('products:id,name');
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update(Arr::except($data, ['product_ids']));
        $this->syncProducts($coupon, $data);

        return $coupon->load('products:id,name');
    }

    /**
     * Rattache les articles visés.
     *
     * Clé absente = la cible n'est pas touchée : une mise à jour partielle qui
     * ne parle que du libellé ne doit pas déscoper le coupon. Tableau vide
     * explicite = le code redevient applicable à tout le panier.
     */
    private function syncProducts(Coupon $coupon, array $data): void
    {
        if (! array_key_exists('product_ids', $data)) {
            return;
        }

        $coupon->products()->sync($data['product_ids'] ?? []);
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    /**
     * Valide un code promo et retourne ses caractéristiques.
     *
     * Le montant de la remise n'est renvoyé que si l'appelant fournit ses
     * lignes : un coupon ciblé, ou un panier contenant des articles déjà
     * remisés, ne se chiffre pas sans savoir ce qui est acheté. Le front
     * calculait ce montant lui-même (`sous-total × valeur / 100`) et annonçait
     * donc une réduction que la commande ne tenait pas.
     *
     * @param  int|null  $customerId  cliente qui saisit le code, pour les
     *                                coupons nominatifs
     * @param  array<array{product_id:int,variant_id:int|null,quantity:int}>  $items
     * @throws \RuntimeException si le code est invalide, expiré, épuisé,
     *                           réservé à quelqu'un d'autre, ou sans effet sur
     *                           les articles fournis
     */
    public function check(string $code, ?int $customerId = null, array $items = []): array
    {
        $coupon = Coupon::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first();

        if (! $coupon) {
            throw new \RuntimeException('Code promo invalide.');
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            throw new \RuntimeException('Ce code promo a expiré.');
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            throw new \RuntimeException("Ce code promo a atteint sa limite d'utilisation.");
        }

        // Coupon nominatif : refusé ici, sinon la cliente le verrait accepté
        // au panier puis silencieusement ignoré au moment de payer.
        // Le message reste volontairement vague — confirmer l'existence d'un
        // code réservé à quelqu'un d'autre n'apporte rien.
        if ($coupon->customer_id && $coupon->customer_id !== $customerId) {
            throw new \RuntimeException("Ce code promo n'est pas utilisable sur ce compte.");
        }

        $coupon->load('products:id,name');

        $reponse = [
            'code'     => $coupon->code,
            'type'     => $coupon->type,
            'discount' => $coupon->type === 'percentage' ? (float) $coupon->value : null,
            'value'    => (float) $coupon->value,
            // Articles visés : de quoi afficher « valable sur X » et expliquer
            // une remise plus faible que le taux annoncé.
            'products' => $coupon->products->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name,
            ])->all(),
            'targeted' => $coupon->products->isNotEmpty(),
        ];

        if ($items === []) {
            return $reponse;
        }

        $lignes = $this->resolveLines($items);
        ['base' => $base, 'discount' => $montant] = CouponDiscount::forLines($coupon, $lignes);

        if ($montant <= 0) {
            throw new \RuntimeException($this->raisonNonApplicable($coupon));
        }

        // Le minimum d'achat porte sur la commande entière, pas sur l'assiette
        // du coupon : c'est un seuil de panier, pas une condition de remise.
        $sousTotal = array_sum(array_map(
            fn (array $l) => $l['unit_price'] * $l['quantity'],
            $lignes,
        ));

        if (! $coupon->isValid($sousTotal)) {
            throw new \RuntimeException(
                'Ce code promo demande un minimum de '
                . number_format((float) $coupon->minimum_amount, 0, ',', ' ') . ' FCFA.'
            );
        }

        return $reponse + [
            'discount_amount' => $montant,
            'eligible_base'   => $base,
        ];
    }

    /**
     * Transforme des lignes de charge utile en lignes tarifées.
     *
     * Le prix vient de SellingPrice, comme partout ailleurs : le coupon se
     * calcule sur ce qui sera réellement facturé, promotions déduites.
     *
     * @param  array<array{product_id:int,variant_id:int|null,quantity:int}>  $items
     * @return array<array{product: Product|null, variant: ProductVariant|null, unit_price: float, quantity: int}>
     */
    private function resolveLines(array $items): array
    {
        $produits = Product::whereIn('id', collect($items)->pluck('product_id')->unique())
            ->get()->keyBy('id');

        $variantes = ProductVariant::whereIn(
            'id', collect($items)->pluck('variant_id')->filter()->unique()
        )->get()->keyBy('id');

        return collect($items)
            ->filter(fn (array $i) => $produits->has($i['product_id']))
            ->map(function (array $i) use ($produits, $variantes) {
                $produit  = $produits[$i['product_id']];
                $variante = isset($i['variant_id']) ? $variantes->get($i['variant_id']) : null;

                return [
                    'product'    => $produit,
                    'variant'    => $variante,
                    'unit_price' => SellingPrice::for($produit, $variante),
                    'quantity'   => (int) $i['quantity'],
                ];
            })
            ->values()
            ->all();
    }

    /** Pourquoi le code n'accorde rien sur ces articles. */
    private function raisonNonApplicable(Coupon $coupon): string
    {
        if ($coupon->products->isNotEmpty()) {
            return "Ce code promo ne s'applique qu'à : "
                . $coupon->products->pluck('name')->implode(', ') . '.';
        }

        return "Ce code promo ne s'applique à aucun article de votre panier : "
            . 'les articles déjà en promotion en sont exclus.';
    }
}
