<?php

namespace Modules\Order\Services\OrderCreation;

use Modules\Cart\Models\Cart;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Modules\Catalog\Support\WeeklyAllocationGuard;
use Modules\Order\Models\Order;

/**
 * Sortie de stock d'une commande passée depuis le panier.
 *
 * Le décrément passe par le registre : c'est lui qui écrit le compteur ET la
 * ligne d'historique, de sorte qu'on puisse toujours dire quelle commande a
 * consommé quel article.
 */
class StockManager
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly WeeklyAllocationGuard $allocations,
    ) {}

    public function updateStock(Cart $cart, ?Order $order = null): void
    {
        /** @var array<int, int> $dejaRetenu  product_id => quantité déjà contrôlée */
        $dejaRetenu = [];

        foreach ($cart->items as $item) {
            $suivi = (bool) $item->product->track_stock;

            // Contrôle de disponibilité AVANT décrément (évite la survente /
            // stock négatif). L'appelant s'exécute en transaction → rollback,
            // et les contrôleurs traduisent la RuntimeException en 422.
            //
            // Le registre refuse lui aussi de passer sous zéro, mais sans le nom
            // du produit : ce contrôle-ci existe pour le message rendu à la
            // cliente, pas seulement pour la sécurité du compteur.
            if ($suivi) {
                $available = $item->variant ? (int) $item->variant->stock : (int) $item->product->stock;
                if ($available < $item->quantity) {
                    throw new \RuntimeException(
                        "Stock insuffisant pour « {$item->product->name} » (disponible : {$available}, demandé : {$item->quantity})."
                    );
                }
            }

            // Quota hebdomadaire de la boutique en ligne. Distinct du stock :
            // la marchandise existe, c'est la boutique qui a décidé de n'en
            // mettre qu'une partie en vente cette semaine. Le message doit le
            // dire, sinon la cliente croit à une rupture.
            //
            // Appliqué même quand le stock n'est pas suivi : l'allocation est
            // une décision de vente, pas un comptage. La commande rapide et la
            // fiche article l'appliquent déjà ainsi — le panier était le seul à
            // l'ignorer, et une cliente obtenait deux réponses selon le parcours.
            //
            // Les déclinaisons d'un même article se cumulent : l'allocation
            // porte sur l'article, deux et deux ne passent pas sous un plafond
            // de trois parce qu'elles sont en rose et en nude.
            $refus = $this->allocations->rejection(
                $item->product_id,
                $item->product->name,
                (int) $item->quantity,
                $dejaRetenu[$item->product_id] ?? 0,
                // Les lignes de cette commande sont déjà écrites : elles ne
                // doivent pas compter comme vendues avant d'être contrôlées.
                $order?->id,
            );

            if ($refus !== null) {
                throw new \RuntimeException($refus);
            }

            $dejaRetenu[$item->product_id] = ($dejaRetenu[$item->product_id] ?? 0) + (int) $item->quantity;

            if (! $suivi) {
                continue;
            }

            $this->ledger->out(
                $item->product,
                $item->variant,
                $item->quantity,
                StockMovement::TYPE_SALE,
                [
                    'reference' => $order,
                    'reason'    => $order ? "Commande {$order->number}" : null,
                ],
            );
        }
    }
}
