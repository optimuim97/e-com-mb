<?php

namespace Modules\Catalog\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Events\StockAlertRaised;
use Modules\Catalog\Models\StockMovement;

/**
 * Point de passage unique pour toucher au stock.
 *
 * Le compteur et son historique doivent bouger ensemble : un `decrement()`
 * lancé ailleurs laisserait un trou dans le registre, et un registre troué ne
 * sert à rien — on ne saurait plus lequel des deux croire. Toute écriture de
 * stock passe donc par ici, et repart avec sa ligne d'historique.
 *
 * La quantité est signée : positive pour une entrée, négative pour une sortie.
 */
class StockLedger
{
    /**
     * Applique un mouvement et l'enregistre.
     *
     * @param  int    $quantity  signée : + entrée, − sortie
     * @param  array{reason?: ?string, unit_cost?: float|int|null, reference?: ?Model, user_id?: ?int, allow_negative?: bool}  $context
     *
     * @return StockMovement|null  null si le produit ne suit pas son stock
     *
     * @throws \RuntimeException  si le mouvement ferait passer le stock sous zéro
     */
    public function record(
        Product $product,
        ?ProductVariant $variant,
        int $quantity,
        string $type,
        array $context = []
    ): ?StockMovement {
        // Produit sans suivi de stock : il n'y a pas de compteur à déplacer,
        // donc rien à raconter.
        if (! $product->track_stock) {
            return null;
        }

        if ($quantity === 0) {
            return null;
        }

        return DB::transaction(function () use ($product, $variant, $quantity, $type, $context) {
            // Verrou sur la ligne qui porte le compteur : deux commandes
            // simultanées sur le dernier article liraient sinon le même « avant »
            // et le registre annoncerait deux fois le même stock final.
            $porteur = $variant
                ? ProductVariant::whereKey($variant->id)->lockForUpdate()->first()
                : Product::whereKey($product->id)->lockForUpdate()->first();

            if (! $porteur) {
                return null;
            }

            $avant = (int) $porteur->stock;
            $apres = $avant + $quantity;

            if ($apres < 0 && ! ($context['allow_negative'] ?? false)) {
                throw new \RuntimeException(
                    "Stock insuffisant pour « {$product->name} » (disponible : {$avant}, demandé : " . abs($quantity) . ').'
                );
            }

            $porteur->forceFill(['stock' => $apres])->save();

            // Les objets déjà chargés par l'appelant doivent refléter le nouveau
            // compteur, sinon un contrôle effectué juste après lirait l'ancien.
            if ($variant) {
                $variant->stock = $apres;
            } else {
                $product->stock = $apres;
            }

            $reference = $context['reference'] ?? null;

            $mouvement = StockMovement::create([
                'product_id'     => $product->id,
                'variant_id'     => $variant?->id,
                'type'           => $type,
                'quantity'       => $quantity,
                'stock_before'   => $avant,
                'stock_after'    => $apres,
                'unit_cost'      => $context['unit_cost'] ?? null,
                'reason'         => $context['reason']    ?? null,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id'   => $reference?->getKey(),
                'user_id'        => $context['user_id'] ?? auth()->id(),
            ]);

            $this->alerterSiSeuilFranchi($product, $variant, $mouvement);

            return $mouvement;
        });
    }

    /**
     * Prévient d'une rupture ou d'un stock bas, au moment où le seuil est
     * franchi vers le bas.
     *
     * Uniquement au franchissement : alerter tant qu'on reste sous le seuil
     * ferait partir une notification à chaque vente de la journée, et une
     * alerte qu'on reçoit dix fois est une alerte qu'on cesse de lire.
     * Remonter au-dessus du seuil réarme l'alerte.
     */
    private function alerterSiSeuilFranchi(Product $product, ?ProductVariant $variant, StockMovement $mouvement): void
    {
        // Une entrée de stock ne peut pas provoquer de rupture.
        if ($mouvement->quantity >= 0) {
            return;
        }

        $avant = $mouvement->stock_before;
        $apres = $mouvement->stock_after;
        $seuil = max(0, (int) $product->low_stock_threshold);

        $niveau = match (true) {
            $apres <= 0 && $avant > 0 => StockAlertRaised::LEVEL_OUT,
            $seuil > 0 && $apres <= $seuil && $avant > $seuil => StockAlertRaised::LEVEL_LOW,
            default => null,
        };

        if ($niveau === null) {
            return;
        }

        StockAlertRaised::dispatch($product, $variant, $mouvement, $niveau);
    }

    /** Sortie de stock. La quantité attendue est positive. */
    public function out(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): ?StockMovement
    {
        return $this->record($product, $variant, -abs($quantity), $type, $context);
    }

    /** Entrée en stock. La quantité attendue est positive. */
    public function in(Product $product, ?ProductVariant $variant, int $quantity, string $type, array $context = []): ?StockMovement
    {
        return $this->record($product, $variant, abs($quantity), $type, $context);
    }

    /**
     * Cale le compteur sur une valeur constatée (comptage physique) et
     * enregistre l'écart. Renvoie null si le stock était déjà juste.
     */
    public function adjustTo(Product $product, ?ProductVariant $variant, int $compte, array $context = []): ?StockMovement
    {
        $actuel = (int) ($variant?->stock ?? $product->stock);

        return $this->record(
            $product,
            $variant,
            $compte - $actuel,
            $context['type'] ?? StockMovement::TYPE_ADJUSTMENT,
            $context + ['allow_negative' => true],
        );
    }
}
