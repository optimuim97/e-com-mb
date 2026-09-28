<?php

namespace Modules\Catalog\Support;

use Modules\Catalog\Services\WeeklyStockBoard;

/**
 * Refuse une commande en ligne qui dépasserait l'allocation de la semaine.
 *
 * Le plafond ne vaut **que pour la boutique en ligne**. Au comptoir, la
 * vendeuse puise dans la réserve : lui appliquer le quota du site lui
 * interdirait de vendre un article qu'elle a sous la main.
 *
 * Le contrôle ne remplace pas celui du stock, il s'y ajoute. Une allocation
 * généreuse ne fait pas apparaître de marchandise, et les deux messages
 * doivent rester distincts : « plus de stock » et « quota de la semaine
 * atteint » n'appellent pas la même action de la boutique.
 */
class WeeklyAllocationGuard
{
    public function __construct(private readonly WeeklyStockBoard $board) {}

    /**
     * Message d'erreur si la ligne dépasse l'allocation, sinon null.
     *
     * @param  int       $alreadyInOrder  quantité du même article déjà retenue
     *                                    dans la commande ou le panier en cours
     * @param  int|null  $exceptOrderId   commande en cours d'écriture, dont les
     *                                    lignes ne comptent pas comme déjà vendues
     */
    public function rejection(
        int $productId,
        string $productName,
        int $quantity,
        int $alreadyInOrder = 0,
        ?int $exceptOrderId = null,
    ): ?string {
        $reste = $this->board->remainingFor($productId, exceptOrderId: $exceptOrderId);

        // Aucune allocation : la semaine n'est pas plafonnée pour cet article.
        if ($reste === null) {
            return null;
        }

        $demande = $quantity + $alreadyInOrder;

        if ($demande <= $reste) {
            return null;
        }

        if ($reste === 0) {
            return "« {$productName} » n'est plus disponible à la vente en ligne cette semaine.";
        }

        return "« {$productName} » : il n'en reste que {$reste} à la vente en ligne cette semaine "
             . "(demandé : {$demande}).";
    }
}
