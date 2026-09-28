<?php

namespace Modules\Catalog\Services;

use Modules\Catalog\Models\Product;

/**
 * Ce que la boutique en ligne peut vendre d'un article, maintenant.
 *
 * La fiche affichait le stock physique, alors que la commande était plafonnée
 * par l'allocation de la semaine. La cliente voyait « 40 en stock », remplissait
 * son panier, saisissait son adresse — et se voyait refuser à la validation.
 * C'est le pire moment pour refuser : un article marqué indisponible coûte une
 * visite, un refus au paiement coûte la cliente.
 *
 * Trois états, parce qu'ils n'appellent pas la même réaction :
 *   · `available`     — se commande ;
 *   · `quota_reached` — la marchandise existe, la boutique a vendu en ligne ce
 *                       qu'elle avait décidé pour la semaine. « De retour
 *                       lundi », et non « épuisé », qui serait faux et ne fait
 *                       pas revenir ;
 *   · `out_of_stock`  — plus rien en réserve. Lundi n'y changera rien.
 *
 * Lu une fois par requête pour tous les articles alloués, puis résolu en
 * mémoire : un catalogue de soixante fiches ne doit pas coûter soixante
 * requêtes. Liaison `scoped` et non `singleton` : le cache se vide entre deux
 * requêtes et entre deux tâches de file, sans quoi un worker afficherait
 * indéfiniment le reste de la semaine où il a démarré.
 *
 * Affichage seulement. Le contrôle à l'écriture de la commande reste celui de
 * WeeklyAllocationGuard, qui relit la base : deux clientes peuvent viser le
 * même dernier pot, et seule la base tranche.
 */
class OnlineAvailability
{
    public const AVAILABLE     = 'available';
    public const QUOTA_REACHED = 'quota_reached';
    public const OUT_OF_STOCK  = 'out_of_stock';

    /** @var array<int, int>|null  product_id => reste de l'allocation */
    private ?array $remaining = null;

    public function __construct(private readonly WeeklyStockBoard $board) {}

    /**
     * @return array{status: string, sellable: ?int}
     *         `sellable` nul = aucune limite (stock non suivi, rien d'alloué).
     */
    public function for(Product $product): array
    {
        $reste = $this->remainingFor($product->id);

        // Stock non suivi : la réserve ne borne rien. L'allocation, elle,
        // continue de s'appliquer — c'est une décision de vente, pas un comptage.
        $physique = $product->track_stock ? max(0, (int) $product->effective_stock) : null;

        // L'épuisement prime sur le quota : si la réserve est vide, annoncer un
        // retour lundi serait une promesse que rien ne permet de tenir.
        if ($physique === 0) {
            return ['status' => self::OUT_OF_STOCK, 'sellable' => 0];
        }

        if ($reste === 0) {
            return ['status' => self::QUOTA_REACHED, 'sellable' => 0];
        }

        $bornes = array_filter([$physique, $reste], fn ($v) => $v !== null);

        return [
            'status'   => self::AVAILABLE,
            'sellable' => $bornes === [] ? null : min($bornes),
        ];
    }

    /** Reste de l'allocation, ou null si l'article n'est pas plafonné. */
    public function remainingFor(int $productId): ?int
    {
        $this->remaining ??= $this->board->remainingByProduct();

        return array_key_exists($productId, $this->remaining)
            ? $this->remaining[$productId]
            : null;
    }
}
