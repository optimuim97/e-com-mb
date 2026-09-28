<?php

namespace Modules\Order\Support;

use Illuminate\Support\Carbon;
use Modules\Admin\Models\Setting;
use Modules\Order\Models\Order;

/**
 * Delivery date promised to the customer.
 *
 * Derived from the extraction calendar (ShipmentSheetScope), never computed
 * separately: promising a date the evening sheet will not honour is the surest
 * way to lose a customer — she waits, nothing comes, she does not come back.
 *
 * Two shapes, depending on distance:
 *  - Abidjan: a firm date. The order leaves with the next 21:00 extraction and
 *    is delivered the following day.
 *  - Elsewhere: a range. The departure day is certain (the day after the
 *    extraction), the transit time is not — it is set per zone in the admin.
 *
 * Outside Abidjan, payment is what releases the parcel. Until it is received —
 * or, abroad, until the shipping fee is settled — the date is conditional: it
 * assumes the missing step happens now, and the wording says so.
 */
final class DeliveryEstimate
{
    /** Zones whose transit time is configurable, with their default range in days. */
    public const TRANSIT_DEFAULTS = [
        'interior'      => [1, 2],
        'sous_region'   => [3, 7],
        'international' => [3, 5],
    ];

    /** Setting keys holding a zone's transit range, in calendar days. */
    public static function transitKeys(string $zone): array
    {
        return ["delivery_transit_{$zone}_min", "delivery_transit_{$zone}_max"];
    }

    /**
     * @return array{
     *     zone: string, firm: bool, conditional: bool,
     *     ships_on: string, from: string, to: string
     * }|null  Null once the order is delivered or abandoned.
     */
    public static function for(Order $order, ?Carbon $now = null): ?array
    {
        $now = $now ?? now();

        if (in_array($order->status, ['delivered', 'cancelled', 'refunded'], true)) {
            return null;
        }

        $zone        = ShipmentSheetScope::zoneOf($order);
        $conditional = false;

        if ($order->shipped_at) {
            $shipsOn = Carbon::parse($order->shipped_at)->startOfDay();
        } elseif ($order->exported_at) {
            // Extracted in the evening, out of the shop the next morning.
            $shipsOn = Carbon::parse($order->exported_at)->addDay()->startOfDay();
        } else {
            // Not extracted yet: it goes with the next run from now. Anchoring
            // on the creation or payment date instead would promise a run that
            // has already passed without it.
            $shipsOn     = ShipmentSheetScope::nextRun($zone, $now)->addDay()->startOfDay();
            $conditional = ! self::isReady($order, $zone);
        }

        [$min, $max] = $zone === 'abidjan' ? [0, 0] : self::transit($zone);

        return [
            'zone'        => $zone,
            'firm'        => $zone === 'abidjan',
            'conditional' => $conditional,
            'ships_on'    => $shipsOn->toDateString(),
            'from'        => $shipsOn->copy()->addDays($min)->toDateString(),
            'to'          => $shipsOn->copy()->addDays($max)->toDateString(),
        ];
    }

    /** Is the order still waiting on something before it can leave? */
    private static function isReady(Order $order, string $zone): bool
    {
        if (! ShipmentSheetScope::requiresPayment($zone)) {
            return true;
        }

        if (! $order->paid_at) {
            return false;
        }

        if (in_array($zone, ['sous_region', 'international'], true)) {
            return ! ShippingFeeRequirement::isPending($order);
        }

        return true;
    }

    /**
     * Transit range configured for the zone.
     *
     * An empty or inconsistent value falls back to the default rather than
     * announcing an absurd range.
     *
     * @return array{0: int, 1: int}
     */
    public static function transit(string $zone): array
    {
        $default = self::TRANSIT_DEFAULTS[$zone] ?? [1, 3];
        [$minKey, $maxKey] = self::transitKeys($zone);

        $min = Setting::get($minKey);
        $max = Setting::get($maxKey);

        if (! is_numeric($min) || ! is_numeric($max)) {
            return $default;
        }

        $min = (int) $min;
        $max = (int) $max;

        return $min >= 0 && $min <= $max && $max <= 60 ? [$min, $max] : $default;
    }

    /**
     * The same promise, spelled out in French.
     *
     * Used by the WhatsApp message and the admin: the wording must be identical
     * everywhere, or the customer receives two different dates.
     */
    public static function sentence(?array $estimate): ?string
    {
        if (! $estimate) {
            return null;
        }

        $day = fn (string $date) => Carbon::parse($date)->locale('fr')->isoFormat('dddd D MMMM');

        if ($estimate['firm']) {
            $text = 'Livraison prévue le ' . $day($estimate['from']);
        } elseif ($estimate['from'] === $estimate['to']) {
            $text = 'Arrivée prévue le ' . $day($estimate['from']);
        } else {
            $text = 'Arrivée prévue entre le ' . $day($estimate['from'])
                . ' et le ' . $day($estimate['to']);
        }

        if (! $estimate['conditional']) {
            return $text . '.';
        }

        $abroad = in_array($estimate['zone'], ['sous_region', 'international'], true);

        return $text . ', sous réserve de votre règlement'
            . ($abroad ? ' et des frais de livraison confirmés par notre équipe' : '')
            . '.';
    }
}
