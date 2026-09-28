<?php

namespace Modules\Order\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Modules\Order\Exceptions\NoShippableOrdersException;
use Modules\Order\Exceptions\RoundNotPointedException;
use Modules\Order\Support\ShippingBlockers;

/**
 * Préparation d'une tournée.
 *
 * Le geste que l'agent fait une fois par zone et par jour : il coche des
 * commandes, et il en sort un code, une feuille de route et vingt commandes
 * passées en « expédiée ». Les trois doivent tomber ensemble ou pas du tout —
 * une tournée créée dont les commandes seraient restées « en préparation »
 * enverrait le livreur sur des commandes que l'écran des expéditions
 * proposerait encore à un autre.
 */
class DeliveryRoundService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Crée la tournée à partir des commandes cochées et les expédie.
     *
     * Les commandes qui ne peuvent pas partir sont écartées et rapportées à
     * l'agent, qui décide : les régler, ou les retirer de sa sélection. On ne
     * les embarque pas silencieusement — c'est précisément ce que les
     * garde-fous d'expédition existent pour empêcher.
     *
     * @param  array<int, int>  $orderIds
     * @return array{round: DeliveryRound, rejected: array<int, array{id: int, number: string, reasons: array<int, string>, forceable: bool}>}
     */
    public function create(
        array $orderIds,
        ?string $label = null,
        ?int $courierId = null,
        bool $force = false,
        ?string $notes = null,
        /**
         * Prépare sans faire partir. La sélection est figée et le code
         * attribué, mais rien n'est expédié : c'est la confirmation, le
         * lendemain, qui engage les colis.
         */
        bool $draft = false,
    ): array {
        $orders = Order::with('items')
            ->whereIn('id', $orderIds)
            ->get();

        [$retenues, $ecartees] = $this->trier($orders, $force);

        if ($retenues->isEmpty()) {
            throw new NoShippableOrdersException($ecartees);
        }

        $round = DB::transaction(function () use ($retenues, $label, $courierId, $notes, $draft) {
            $zone = $this->zoneCommune($retenues);

            $round = DeliveryRound::create([
                'code'          => DeliveryRound::generateCode(),
                'label'         => $label,
                'zone_group'    => $zone['group'] ?? null,
                'zone_name'     => $zone['name']  ?? null,
                'courier_id'    => $courierId,
                'status'        => $draft ? DeliveryRound::STATUS_DRAFT : DeliveryRound::STATUS_DISPATCHED,
                // Une tournée en préparation n'a pas de date de départ : la
                // renseigner ferait croire à des colis partis.
                'dispatched_at' => $draft ? null : now(),
                'notes'         => $notes,
            ]);

            $position = 0;
            foreach ($retenues as $order) {
                DeliveryRoundOrder::create([
                    'delivery_round_id' => $round->id,
                    'order_id'          => $order->id,
                    'position'          => ++$position,
                    'status'            => DeliveryRoundOrder::STATUS_PENDING,
                    'amount_expected'   => $this->montantAEncaisser($order),
                ]);
            }

            return $round;
        });

        // Hors transaction : le passage en « expédiée » émet un événement, et
        // un événement émis dans une transaction qui échouerait ensuite
        // annoncerait une expédition qui n'a pas eu lieu.
        if (! $draft) {
            $this->expedier($retenues);
        }

        return [
            'round'    => $round->load(['lines.order.items', 'courier']),
            'rejected' => $ecartees,
        ];
    }

    /**
     * Fait partir une tournée préparée la veille.
     *
     * C'est ici, et pas à la création du brouillon, que les colis quittent la
     * boutique : les garde-fous d'expédition sont donc rejoués au dernier
     * moment. Une commande réglée dans la nuit devient expédiable, une
     * commande annulée entre-temps ne part pas — l'état du matin fait foi, pas
     * celui de 21 h.
     */
    public function dispatch(DeliveryRound $round, ?int $courierId = null, bool $force = false): array
    {
        if ($round->status !== DeliveryRound::STATUS_DRAFT) {
            throw new \RuntimeException('Cette tournée est déjà partie.');
        }

        $orders = $round->lines()->with('order.items')->get()
            ->map(fn (DeliveryRoundOrder $line) => $line->order)
            ->filter();

        [$retenues, $ecartees] = $this->trier($orders, $force, exceptRoundId: $round->id);

        if ($retenues->isEmpty()) {
            throw new NoShippableOrdersException($ecartees);
        }

        DB::transaction(function () use ($round, $retenues, $ecartees, $courierId) {
            // Les commandes écartées quittent la tournée : les y laisser
            // ferait attendre au retour un pointage qui ne viendra jamais.
            if ($ecartees !== []) {
                $round->lines()
                    ->whereIn('order_id', array_column($ecartees, 'id'))
                    ->delete();
            }

            $round->update([
                'courier_id'    => $courierId ?? $round->courier_id,
                'status'        => DeliveryRound::STATUS_DISPATCHED,
                'dispatched_at' => now(),
            ]);
        });

        $this->expedier($retenues);

        return [
            'round'    => $round->fresh(['lines.order.items', 'courier']),
            'rejected' => $ecartees,
        ];
    }

    /** Passe les commandes en « expédiée », hors transaction. */
    private function expedier(Collection $orders): void
    {
        foreach ($orders as $order) {
            if ($order->status !== 'shipped') {
                $this->orderService->updateStatus($order, 'shipped');
            }
        }
    }

    /**
     * Pointe une livraison au retour du livreur.
     *
     * Le statut de la commande suit immédiatement, sans attendre la clôture :
     * un pointage interrompu à mi-parcours — le téléphone sonne, une cliente
     * entre — laisserait sinon dix commandes livrées affichées « expédiée ».
     *
     * Le montant encaissé vaut par défaut le montant attendu. À vingt lignes,
     * exiger la saisie du montant sur chacune ferait abandonner l'écran au
     * profit de la feuille de papier ; l'agent ne corrige que les écarts, et
     * ce sont eux que la clôture met en évidence.
     */
    public function pointLine(
        DeliveryRoundOrder $line,
        string $outcome,
        ?float $collected = null,
        ?string $paymentMethod = null,
        ?string $reason = null,
        ?int $operatorId = null,
    ): DeliveryRoundOrder {
        $livree = $outcome === DeliveryRoundOrder::STATUS_DELIVERED;

        $line->update([
            'status'                   => $outcome,
            // Rien n'est encaissé sur un passage qui a échoué.
            'amount_collected'         => $livree ? ($collected ?? (float) $line->amount_expected) : 0,
            'payment_method_collected' => $livree ? $paymentMethod : null,
            'delivered_at'             => $livree ? now() : null,
            'failure_reason'           => $livree ? null : $reason,
            'confirmed_by'             => $operatorId,
        ]);

        $order = $line->order;

        if ($order) {
            // Un échec ou un report ramène la commande en préparation : elle
            // est physiquement revenue en boutique, et doit réapparaître dans
            // la liste de ceux qui composent les tournées. La laisser
            // « expédiée » la ferait disparaître de tous les écrans.
            $statut = $livree ? 'delivered' : 'processing';

            if ($order->status !== $statut) {
                $this->orderService->updateStatus($order, $statut);
            }
        }

        return $line->fresh(['order']);
    }

    /**
     * Clôture la tournée. Geste à sens unique : ce qui est pointé est arrêté.
     *
     * Refuse tant qu'une livraison n'est pas pointée. Une tournée clôturée à
     * moitié donnerait un écart de caisse qui ne veut rien dire — on ne saurait
     * pas si l'argent manque ou si la ligne n'a simplement pas été vue.
     *
     * @throws RoundNotPointedException
     */
    public function close(DeliveryRound $round): DeliveryRound
    {
        $restantes = $round->lines()
            ->where('status', DeliveryRoundOrder::STATUS_PENDING)
            ->count();

        if ($restantes > 0) {
            throw new RoundNotPointedException($restantes);
        }

        $round->update([
            'status'    => DeliveryRound::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return $round->fresh(['lines.order.items', 'courier']);
    }

    /**
     * Sépare ce qui peut partir de ce qui ne le peut pas.
     *
     * @return array{0: Collection<int, Order>, 1: array<int, array{id: int, number: string, reasons: array<int, string>, forceable: bool}>}
     */
    private function trier(Collection $orders, bool $force, ?int $exceptRoundId = null): array
    {
        $dejaEnTournee = $this->dejaEnTourneeOuverte($orders->pluck('id')->all(), $exceptRoundId);

        $retenues = [];
        $ecartees = [];

        foreach ($orders as $order) {
            // `forcable` distingue les deux natures de refus. Les garde-fous
            // d'expédition — frais à fixer, commande impayée — se lèvent sous
            // la responsabilité de l'agent. Une commande annulée ou déjà
            // engagée ailleurs, non : aucun arbitrage ne la rend livrable, et
            // proposer « expédier quand même » ne ferait que représenter le
            // même refus.
            $forcable = false;
            $raisons  = [];

            if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                $raisons[] = 'Commande ' . ($order->status === 'cancelled' ? 'annulée' : 'remboursée') . '.';
            } elseif ($order->status === 'delivered') {
                $raisons[] = 'Commande déjà livrée.';
            } elseif (isset($dejaEnTournee[$order->id])) {
                // Deux livreurs à la même adresse le même jour : la cliente est
                // livrée deux fois, ou personne ne sait qui a encaissé.
                $raisons[] = 'Déjà dans la tournée ' . $dejaEnTournee[$order->id] . '.';
            } elseif (! $force) {
                $raisons  = ShippingBlockers::for($order);
                $forcable = $raisons !== [];
            }

            if ($raisons === []) {
                $retenues[] = $order;
                continue;
            }

            $ecartees[] = [
                'id'       => $order->id,
                'number'   => $order->number,
                'reasons'  => $raisons,
                'forceable' => $forcable,
            ];
        }

        return [collect($retenues), $ecartees];
    }

    /**
     * Commandes déjà engagées dans une tournée non clôturée.
     *
     * Les brouillons comptent : une commande réservée par l'extraction du soir
     * ne doit pas repartir dans la tournée qu'un autre agent compose en
     * parallèle, sans quoi la confirmation du matin trouverait la commande déjà
     * expédiée ailleurs.
     *
     * `$exceptRoundId` sert à la confirmation, qui rejoue le tri sur les
     * commandes de son propre brouillon : sans cette exception, chacune se
     * verrait refusée au motif qu'elle figure dans la tournée qu'on fait partir.
     *
     * @param  array<int, int>  $orderIds
     * @return array<int, string>  order_id => code de la tournée
     */
    public function openRoundCodes(array $orderIds, ?int $exceptRoundId = null): array
    {
        return $this->dejaEnTourneeOuverte($orderIds, $exceptRoundId);
    }

    private function dejaEnTourneeOuverte(array $orderIds, ?int $exceptRoundId = null): array
    {
        if ($orderIds === []) {
            return [];
        }

        return DeliveryRoundOrder::query()
            ->join('delivery_rounds', 'delivery_rounds.id', '=', 'delivery_round_orders.delivery_round_id')
            ->whereIn('delivery_round_orders.order_id', $orderIds)
            ->whereIn('delivery_rounds.status', [DeliveryRound::STATUS_DRAFT, DeliveryRound::STATUS_DISPATCHED])
            ->where('delivery_round_orders.status', DeliveryRoundOrder::STATUS_PENDING)
            ->when($exceptRoundId, fn ($q) => $q->where('delivery_rounds.id', '!=', $exceptRoundId))
            ->pluck('delivery_rounds.code', 'delivery_round_orders.order_id')
            ->all();
    }

    /**
     * Ce que le livreur doit rapporter pour cette commande.
     *
     * Une commande déjà réglée ne se paie pas une seconde fois à la porte :
     * elle compte pour zéro. Sans cette distinction, le total de la feuille
     * annoncerait au livreur une somme qu'il ne peut pas encaisser, et le
     * pointage au retour accuserait un manque qui n'existe pas.
     */
    private function montantAEncaisser(Order $order): float
    {
        return $order->paid_at ? 0.0 : (float) $order->total;
    }

    /**
     * Zone de la tournée, si toutes les commandes tombent dans la même.
     *
     * Une sélection mélangée (l'agent a coché large) ne porte pas de zone :
     * mieux vaut un champ vide qu'une étiquette fausse sur la feuille.
     *
     * @return array{group: ?string, name: ?string}
     */
    private function zoneCommune(Collection $orders): array
    {
        $zones = $orders->map(function (Order $order) {
            $addr = $order->shipping_address ?? [];
            $city    = $addr['city']    ?? '';
            $commune = $addr['commune'] ?? null;

            if (! $city && ! $commune) {
                return null;
            }

            $quote = DeliveryZone::quote($city, $addr['country'] ?? 'CI', null, $commune);

            return $quote ? ['group' => $quote['group'], 'name' => $quote['zone_name']] : null;
        })->filter()->unique(fn ($z) => $z['group'] . '|' . $z['name'])->values();

        return $zones->count() === 1 ? $zones->first() : ['group' => null, 'name' => null];
    }
}
