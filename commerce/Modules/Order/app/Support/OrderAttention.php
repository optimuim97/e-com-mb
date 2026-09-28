<?php

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Order\Models\Order;

/**
 * Orders that need a human, and why.
 *
 * The fear behind this screen is losing a customer to silence: an order nobody
 * called back, a payment nobody chased, a parcel that left and never arrived.
 * Each rule below has a deadline; past it, the order surfaces here on its own
 * instead of waiting to be noticed.
 *
 * Two clocks, deliberately:
 *  - what the shop owes is counted in opening hours (BusinessHours) — nobody
 *    was there at the weekend, and an alert nobody could have avoided teaches
 *    the shop to ignore alerts;
 *  - what the customer owes is counted in calendar days — our closing days do
 *    not give her more time to pay.
 *
 * Nothing here writes, sends or cancels anything: it states what is late. The
 * two gestures that reach a customer — cancelling an unpaid order and
 * apologising for a late parcel — stay in the agent's hands, on this screen.
 */
final class OrderAttention
{
    /** Abidjan order still unconfirmed when its evening run has passed. */
    public const TO_CALL = 'to_call';

    /** Outside Abidjan nothing ships unpaid, so an unpaid order is stuck. */
    public const UNPAID = 'unpaid';

    /** Unpaid long enough that the shop should decide to let it go. */
    public const CANCEL_SUGGESTED = 'cancel_suggested';

    /** Abroad, the total is not firm until an agent sets the shipping fee. */
    public const FEE_PENDING = 'fee_pending';

    /** Ready, and yet absent from the run it belonged to — the classic miss. */
    public const MISSED_EXTRACTION = 'missed_extraction';

    /** Gone out, past the date promised to the customer, still not delivered. */
    public const LATE_DELIVERY = 'late_delivery';

    /** Statuses where something is still owed to the customer. */
    private const OPEN = ['pending', 'confirmed', 'processing', 'shipped'];

    /** Shop-side deadline for setting a negotiated shipping fee, in opening hours. */
    private const FEE_HOURS = 24;

    /** Customer-side deadlines for an unpaid order, in calendar days. */
    private const UNPAID_DAYS = 1;
    private const CANCEL_DAYS = 7;

    /** Grace granted on a promised delivery date, in calendar days. */
    private const DELIVERY_GRACE_DAYS = 2;

    /**
     * Orders worth examining.
     *
     * The rules are then applied in PHP rather than in SQL: they mix the
     * extraction calendar, the promised date and two different clocks, and a
     * query trying to say all that would be unreadable and wrong. The volume is
     * a few hundred open orders — the screen loads them in one pass.
     */
    public static function query(): Builder
    {
        return Order::with(['items', 'user'])
            ->whereIn('status', self::OPEN)
            // Archivée : rangée par la boutique, elle n'est plus à traiter.
            ->whereNull('archived_at')
            ->orderBy('created_at');
    }

    /**
     * Why this order needs a human, most pressing first. Empty when all is well.
     *
     * @return array<int, array{code: string, label: string, level: string, since: Carbon, hint: string}>
     */
    public static function for(Order $order, ?Carbon $now = null): array
    {
        $now = $now ?? now();

        if (! in_array($order->status, self::OPEN, true) || $order->archived_at) {
            return [];
        }

        $zone  = ShipmentSheetScope::zoneOf($order);
        $items = array_values(array_filter([
            self::lateDelivery($order, $now),
            self::missedExtraction($order, $zone, $now),
            self::feePending($order, $now),
            self::unpaid($order, $zone, $now),
            self::toCall($order, $zone, $now),
        ]));

        // Le plus grave d'abord, puis le plus ancien : c'est l'ordre dans lequel
        // l'agente doit s'en occuper.
        usort($items, function (array $a, array $b) {
            $grave = ($b['level'] === 'critical' ? 1 : 0) <=> ($a['level'] === 'critical' ? 1 : 0);

            return $grave !== 0 ? $grave : $a['since']->getTimestamp() <=> $b['since']->getTimestamp();
        });

        return $items;
    }

    /** @return array<string, mixed>|null */
    private static function toCall(Order $order, string $zone, Carbon $now): ?array
    {
        if ($zone !== 'abidjan' || $order->status !== 'pending') {
            return null;
        }

        // Une commande d'Abidjan se confirme au téléphone avant l'extraction du
        // soir. Celle-ci passée, plus personne ne la prépare.
        $due = ShipmentSheetScope::nextRun($zone, $order->created_at);

        if ($now->lt($due)) {
            return null;
        }

        return self::item(
            self::TO_CALL,
            'À appeler : commande non confirmée, son extraction est passée.',
            $now->gte(ShipmentSheetScope::nextRun($zone, $due->copy()->addMinute())),
            $due,
            'Appeler la cliente, puis passer la commande en préparation.',
        );
    }

    /** @return array<string, mixed>|null */
    private static function unpaid(Order $order, string $zone, Carbon $now): ?array
    {
        if (! ShipmentSheetScope::requiresPayment($zone) || $order->paid_at || $order->status === 'shipped') {
            return null;
        }

        $days = (int) $order->created_at->diffInDays($now);

        if ($days >= self::CANCEL_DAYS) {
            return self::item(
                self::CANCEL_SUGGESTED,
                'Non payée depuis ' . $days . ' jours : annulation à décider.',
                true,
                $order->created_at->copy()->addDays(self::CANCEL_DAYS),
                'Relancer une dernière fois, ou annuler depuis la liste des commandes.',
            );
        }

        if ($days < self::UNPAID_DAYS) {
            return null;
        }

        return self::item(
            self::UNPAID,
            'En attente de règlement depuis ' . $days . ' jour(s).',
            $days >= 3,
            $order->created_at->copy()->addDays(self::UNPAID_DAYS),
            'Rien ne part ' . lcfirst(HomeZone::outsideLabel()) . ' sans règlement : relancer la cliente.',
        );
    }

    /** @return array<string, mixed>|null */
    private static function feePending(Order $order, Carbon $now): ?array
    {
        if (! ShippingFeeRequirement::isPending($order)) {
            return null;
        }

        $due = BusinessHours::add($order->created_at, self::FEE_HOURS);

        if ($now->lt($due)) {
            return null;
        }

        return self::item(
            self::FEE_PENDING,
            "Frais de livraison à fixer : le total n'est pas ferme.",
            $now->gte(BusinessHours::add($due, self::FEE_HOURS)),
            $due,
            'Arrêter les frais avec la cliente, puis les saisir sur la commande.',
        );
    }

    /**
     * Ready to go, never extracted, and its run has passed.
     *
     * The one alert with no delay: the order was due out that evening and is on
     * no sheet. It would never come back into a window on its own.
     *
     * @return array<string, mixed>|null
     */
    private static function missedExtraction(Order $order, string $zone, Carbon $now): ?array
    {
        if ($order->exported_at || $order->status === 'shipped') {
            return null;
        }

        if (ShipmentSheetScope::requiresPayment($zone)) {
            // Bloquée par le règlement ou les frais : déjà signalée pour ça.
            if (! $order->paid_at || ShippingFeeRequirement::isPending($order)) {
                return null;
            }
        }

        $reference = ShipmentSheetScope::windowColumn($zone) === 'created_at'
            ? $order->created_at
            : $order->paid_at;

        $due = ShipmentSheetScope::nextRun($zone, $reference);

        if ($now->lt($due)) {
            return null;
        }

        return self::item(
            self::MISSED_EXTRACTION,
            "Prête mais absente de l'extraction du " . $due->format('d/m') . '.',
            true,
            $due,
            'Extraire la zone depuis la liste des commandes.',
        );
    }

    /** @return array<string, mixed>|null */
    private static function lateDelivery(Order $order, Carbon $now): ?array
    {
        if ($order->status !== 'shipped' || $order->delivered_at) {
            return null;
        }

        $estimate = DeliveryEstimate::for($order, $now);

        if (! $estimate) {
            return null;
        }

        $due = Carbon::parse($estimate['to'])->endOfDay()->addDays(self::DELIVERY_GRACE_DAYS);

        if ($now->lt($due)) {
            return null;
        }

        return self::item(
            self::LATE_DELIVERY,
            'Expédiée et non livrée, au-delà de la date annoncée.',
            $now->gte($due->copy()->addDays(self::DELIVERY_GRACE_DAYS)),
            $due,
            'Prendre des nouvelles du colis, puis annoncer la nouvelle date à la cliente.',
        );
    }

    /** @return array<string, mixed> */
    private static function item(string $code, string $label, bool $critical, Carbon $since, string $hint): array
    {
        return [
            'code'  => $code,
            'label' => $label,
            // Premier dépassement en orange, second en rouge : l'agente voit
            // d'un coup d'œil ce qui traîne depuis trop longtemps.
            'level' => $critical ? 'critical' : 'warning',
            'since' => $since,
            'hint'  => $hint,
        ];
    }
}
