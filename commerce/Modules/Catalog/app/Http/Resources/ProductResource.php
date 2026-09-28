<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Services\OnlineAvailability;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disponibilite = app(OnlineAvailability::class)->for($this->resource);
        $backOffice    = $request->is('api/admin/*');

        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'short_description' => $this->short_description,
            'price'             => $this->price,
            'compare_price'     => $this->compare_price,
            'discount_percent'  => $this->compare_price
                ? round((1 - $this->price / $this->compare_price) * 100)
                : null,
            'sku'               => $this->sku,
            // Finance privée : le coût d'achat (base de la marge) n'est exposé
            // qu'aux utilisateurs disposant de `finance.view`.
            'cost_price'        => $this->when(
                (bool) $request->user()?->can('finance.view'),
                fn () => $this->cost_price
            ),
            // Deux publics, deux nombres.
            //
            // Le back-office lit le stock physique, net des commandes en cours
            // (le décompte a lieu à la création, pas à l'expédition) : c'est ce
            // qui est en rayon, et l'écart avec le vendable est justement ce que
            // la gérante surveille.
            //
            // La boutique lit ce qu'elle accepte de vendre : la réserve bornée
            // par l'allocation de la semaine. Lui montrer le physique, c'était
            // annoncer « 40 en stock » pour un article dont le quota était
            // atteint, et refuser la cliente au paiement. Nul = aucune limite.
            'stock'             => $backOffice ? $this->effective_stock : $disponibilite['sellable'],
            'sellable_stock'    => $disponibilite['sellable'],
            // `available`, `quota_reached` (« de retour lundi ») ou
            // `out_of_stock` (« épuisé »). C'est ce champ, et non un
            // `stock === 0`, qui décide de l'affichage : un article non suivi
            // porte un stock à zéro et se vend pourtant.
            'availability'      => $disponibilite['status'],
            // Rang de la boutique : l'écran d'administration s'en sert pour
            // restituer l'ordre après un glisser-déposer.
            'position'          => (int) $this->position,
            // Ce qui est vendu mais pas encore expédié, et le total qui devrait
            // se trouver en rayon. Renseignés seulement là où la requête les a
            // agrégés (listing admin) — ailleurs, mieux vaut rien qu'un zéro
            // trompeur.
            //
            // Un SUM sans ligne correspondante vaut NULL, pas 0 : c'est la
            // PRÉSENCE de l'attribut qui dit si l'agrégat a tourné, pas sa
            // valeur. Se fier à `!== null` masquait les produits jamais
            // commandés, ceux-là mêmes qu'on veut voir en rayon.
            'reserved'          => $this->when($this->aAgregeLeReserve(), fn () => (int) $this->reserved_quantity),
            'physical_stock'    => $this->when(
                $this->aAgregeLeReserve(),
                fn () => (int) $this->effective_stock + (int) $this->reserved_quantity
            ),
            'type'              => $this->type,
            'has_variants'      => $this->has_variants,
            'is_active'         => $this->is_active,
            'is_featured'       => $this->is_featured,
            'is_new'            => $this->is_new ?? false,
            'is_bestseller'     => $this->is_bestseller ?? false,
            // Flash sale
            'is_flash_sale'     => $this->is_flash_sale ?? false,
            'is_flash_active'   => $this->is_flash_active,
            'flash_price'       => $this->flash_price,
            'flash_starts_at'   => $this->flash_starts_at,
            'flash_ends_at'     => $this->flash_ends_at,
            'effective_price'   => $this->effective_price,
            // Promotion automatique (sans code). `is_discounted` permet au
            // front de barrer le prix sans avoir à comparer lui-même.
            'is_discounted'     => $this->is_discounted,
            'promotion'         => $this->whenNotNull(
                $this->active_promotion ? [
                    'id'            => $this->active_promotion->id,
                    'name'          => $this->active_promotion->name,
                    'type'          => $this->active_promotion->type,
                    'discount_type' => $this->active_promotion->discount_type,
                    'value'         => $this->active_promotion->value,
                    // Libellé saisi en administration (« Déstockage »). Le
                    // pourcentage, lui, reste calculé côté front : une remise
                    // propre à l'article ne correspond pas forcément au taux
                    // annoncé sur l'opération.
                    'banner_label'  => $this->active_promotion->banner_label,
                    'ends_at'       => $this->active_promotion->ends_at,
                ] : null
            ),
            'category'          => new CategoryResource($this->whenLoaded('category')),
            'categories'        => CategoryResource::collection($this->whenLoaded('categories')),
            'product_line'      => new ProductLineResource($this->whenLoaded('productLine')),
            'product_lines'     => ProductLineResource::collection($this->whenLoaded('productLines')),
            'images'            => ProductImageResource::collection($this->whenLoaded('images')),
            'variants'          => VariantResource::collection($this->whenLoaded('variants')),
            'attributes'        => $this->whenLoaded('attributes', fn() =>
                $this->attributes->map(fn($attr) => [
                    'id'     => $attr->id,
                    'name'   => $attr->name,
                    'slug'   => $attr->slug,
                    'values' => $attr->values->map(fn($v) => [
                        'id'        => $v->id,
                        'value'     => $v->value,
                        'color_hex' => $v->color_hex,
                    ]),
                ])
            ),
            'rating_avg'        => $this->reviews_avg_rating
                ? round((float) $this->reviews_avg_rating, 1)
                : null,
            'reviews_count'     => (int) ($this->reviews_count ?? 0),
            'created_at'        => $this->created_at,
        ];
    }

    /** L'appelant a-t-il demandé l'agrégat du stock réservé ? */
    private function aAgregeLeReserve(): bool
    {
        return array_key_exists('reserved_quantity', $this->resource->getAttributes());
    }
}
