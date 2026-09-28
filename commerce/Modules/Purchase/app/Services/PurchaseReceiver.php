<?php

namespace Modules\Purchase\Services;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderItem;

/**
 * Réception d'un bon d'achat : les articles entrent en stock, les coûts se figent.
 *
 * Trois calculs s'enchaînent, dans cet ordre :
 *
 *  1. **Prix de revient unitaire** — coût d'achat de la ligne, augmenté de sa
 *     quote-part des frais annexes (transport, douane). Répartition au prorata
 *     de la VALEUR de la ligne, pas de la quantité : c'est la convention
 *     retenue ici. Répartir au nombre d'articles ferait porter le même
 *     transport à un savon qu'à un sérum, et fausserait la marge du moins cher.
 *
 *  2. **Entrée en stock** — par le registre, avec le prix de revient comme coût
 *     unitaire. L'entrée est donc consultable dans l'historique des mouvements
 *     au même titre qu'une vente.
 *
 *  3. **Coût moyen unitaire pondéré (CMUP)** — mélange de ce qui était déjà en
 *     rayon et de ce qui vient d'arriver. C'est lui qui donne une marge
 *     honnête quand le fournisseur change ses prix : le `cost_price` saisi une
 *     fois pour toutes ne bougeait jamais.
 */
class PurchaseReceiver
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * Réceptionne le bon : stock, prix de revient et CMUP.
     *
     * @throws \RuntimeException  si le bon n'est plus réceptionnable
     */
    public function receive(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status === PurchaseOrder::STATUS_RECEIVED) {
            throw new \RuntimeException('Ce bon d\'achat a déjà été réceptionné.');
        }

        if ($order->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new \RuntimeException('Ce bon d\'achat est annulé : rien à réceptionner.');
        }

        $order->loadMissing(['items.product', 'items.variant']);

        if ($order->items->isEmpty()) {
            throw new \RuntimeException('Ce bon d\'achat ne contient aucune ligne.');
        }

        DB::transaction(function () use ($order) {
            $valeurLignes = (float) $order->items->sum(fn (PurchaseOrderItem $l) => (float) $l->total_cost);
            $fraisAnnexes = $order->extraCosts();

            foreach ($order->items as $ligne) {
                $produit = $ligne->product;
                if (! $produit) {
                    continue;   // produit retiré du catalogue depuis la saisie
                }

                $revientUnitaire = $this->landedUnitCost($ligne, $valeurLignes, $fraisAnnexes);

                $ligne->forceFill(['landed_unit_cost' => $revientUnitaire])->save();

                $this->ledger->in(
                    $produit,
                    $ligne->variant,
                    $ligne->quantity,
                    StockMovement::TYPE_PURCHASE,
                    [
                        'reference' => $order,
                        'unit_cost' => $revientUnitaire,
                        'reason'    => "Bon d'achat {$order->number}",
                    ],
                );

                $this->updateAverageCost($produit, $ligne->variant, $ligne->quantity, $revientUnitaire);
            }

            $order->forceFill([
                'status'      => PurchaseOrder::STATUS_RECEIVED,
                'received_at' => now(),
            ])->save();
        });

        return $order->refresh()->load(['items.product', 'items.variant', 'supplier']);
    }

    /**
     * Prix de revient unitaire d'une ligne : coût d'achat + quote-part des
     * frais annexes, répartis au prorata de la valeur.
     */
    public function landedUnitCost(PurchaseOrderItem $ligne, float $valeurLignes, float $fraisAnnexes): float
    {
        $coutUnitaire = (float) $ligne->unit_cost;
        $quantite     = max(1, (int) $ligne->quantity);

        // Sans valeur totale, aucune clé de répartition n'a de sens (bon à
        // coût nul : cadeau fournisseur, échantillons).
        if ($valeurLignes <= 0 || $fraisAnnexes <= 0) {
            return round($coutUnitaire, 2);
        }

        $quotePart = $fraisAnnexes * ((float) $ligne->total_cost / $valeurLignes);

        return round($coutUnitaire + $quotePart / $quantite, 2);
    }

    /**
     * Recalcule le coût moyen unitaire pondéré après une entrée.
     *
     * Le stock est lu APRÈS l'entrée (le registre vient de l'appliquer) : on
     * retranche donc la quantité reçue pour retrouver ce qu'il y avait avant.
     * Un stock antérieur nul ou négatif n'a pas de moyenne à conserver — le
     * coût d'entrée devient la référence.
     */
    public function updateAverageCost(
        Product $produit,
        ?ProductVariant $declinaison,
        int $quantiteRecue,
        float $coutRevient
    ): void {
        $porteur = $declinaison
            ? ProductVariant::find($declinaison->id)
            : Product::find($produit->id);

        if (! $porteur) {
            return;
        }

        $stockApres = (int) $porteur->stock;
        $stockAvant = $stockApres - $quantiteRecue;

        $moyenneAvant = $porteur->average_cost !== null
            ? (float) $porteur->average_cost
            : (float) ($produit->cost_price ?? 0);

        $nouvelleMoyenne = ($stockAvant > 0 && $moyenneAvant > 0)
            ? (($stockAvant * $moyenneAvant) + ($quantiteRecue * $coutRevient)) / ($stockAvant + $quantiteRecue)
            : $coutRevient;

        $porteur->forceFill(['average_cost' => round($nouvelleMoyenne, 2)])->save();

        // Le produit porte aussi le coût de référence affiché dans le catalogue.
        // On l'aligne sur la dernière entrée : sans cela, la fiche produit
        // continuerait d'annoncer un prix d'achat périmé.
        if (! $declinaison) {
            $produit->forceFill([
                'cost_price'   => round($coutRevient, 2),
                'average_cost' => round($nouvelleMoyenne, 2),
            ])->save();
        }
    }
}
