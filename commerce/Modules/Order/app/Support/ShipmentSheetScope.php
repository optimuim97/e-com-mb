<?php

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Modules\Admin\Models\Setting;
use Modules\Order\Models\Order;

/**
 * Who goes on the evening sheet, and over which period.
 *
 * Single source of the shipping calendar: the 21:00 scheduled task, the manual
 * extraction in the admin and the delivery date promised to customers all read
 * it. Two calendars used to coexist and disagreed — production extracted the
 * interior on Fridays, the day the shop actually ships it.
 *
 * Four zones, set by operations. Each extraction runs at 21:00 on the eve of
 * departure:
 *
 *   Abidjan       Sun → Thu   delivered Mon → Fri, cash on delivery
 *   Interior      Tue, Thu    shipped Wed and Fri, paid orders only
 *   Sous-région   Tue, Thu    rest of Africa, paid, fee settled
 *   International Sun → Wed   DHL express, out Mon → Thu, paid, fee settled
 *
 * The shop is closed at weekends: what arrives after Thursday's run leaves
 * with Sunday evening's, for Monday.
 *
 * The window starts at the zone's **last successful extraction**, never at a
 * fixed "minus 24 hours": Sunday picks up the weekend on its own, and a missed
 * run is caught up by the next one instead of leaving a gap.
 */
class ShipmentSheetScope
{
    public const ZONES = [
        'abidjan'       => 'Abidjan',
        'interior'      => 'Hors Abidjan',
        'sous_region'   => 'Sous-région',
        'international' => 'International (DHL)',
    ];

    /** Extraction days, Carbon style: 0 = Sunday … 6 = Saturday. */
    public const DAYS = [
        'abidjan'       => [0, 1, 2, 3, 4],
        'interior'      => [2, 4],
        'sous_region'   => [2, 4],
        'international' => [0, 1, 2, 3],
    ];

    /**
     * File name slug, as the shop calls each zone. Zone keys themselves stay
     * those already used in production: renaming them would reset each zone's
     * remembered window.
     */
    public const FILE_SLUGS = [
        'abidjan'       => 'abidjan',
        'interior'      => 'hors_abidjan',
        'sous_region'   => 'sous_region',
        'international' => 'dhl',
    ];

    /** Extraction time, shop time (UTC, which Abidjan follows all year). */
    public const RUN_HOUR = 21;

    /**
     * African countries other than Côte d'Ivoire, ISO 3166-1 alpha-2.
     *
     * The whole continent ships as sous-région. Any code missing from this
     * list — including "OTHER", an unspecified country — ships internationally.
     */
    public const AFRICA = [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD', 'KM', 'CG',
        'CD', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW',
        'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA',
        'NE', 'NG', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'SD', 'TZ',
        'TG', 'TN', 'UG', 'ZM', 'ZW', 'EH',
    ];

    /** Statuses of an order that has not left yet. */
    private const TO_SHIP = ['pending', 'confirmed', 'processing'];

    public static function label(string $zone): string
    {
        // La zone locale porte le nom de la ville réglée par la boutique.
        return match ($zone) {
            'abidjan'  => HomeZone::label(),
            'interior' => HomeZone::outsideLabel(),
            default    => self::ZONES[$zone] ?? 'Expéditions',
        };
    }

    /** Suffixe des noms de fichiers d'extraction (« abidjan », « hors_abidjan »…). */
    public static function fileSlug(string $zone): string
    {
        return match ($zone) {
            'abidjan', 'interior' => \Illuminate\Support\Str::slug(self::label($zone), '_'),
            default               => self::FILE_SLUGS[$zone] ?? $zone,
        };
    }

    public static function isKnown(string $zone): bool
    {
        return isset(self::ZONES[$zone]);
    }

    /** Does this zone's carrier come by on that day? */
    public static function isScheduled(string $zone, ?Carbon $day = null): bool
    {
        return in_array(($day ?? now())->dayOfWeek, self::DAYS[$zone], true);
    }

    /** Extraction days in words, for the admin: « mardi, jeudi ». */
    public static function daysLabel(string $zone): string
    {
        $names = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

        return implode(', ', array_map(fn (int $d) => $names[$d], self::DAYS[$zone]));
    }

    /**
     * Outside Abidjan only paid orders ship: the Abidjan courier collects cash
     * at the door, carriers collect nothing.
     */
    public static function requiresPayment(string $zone): bool
    {
        return $zone !== 'abidjan';
    }

    /**
     * Zone of a given order — the reverse of `scoped()`.
     *
     * Used by the promised delivery date, which must follow the very calendar
     * the extraction follows.
     */
    public static function zoneOf(Order $order): string
    {
        $destination = $order->destination
            ?: PaymentAvailability::destination($order->shipping_address);

        if ($destination === PaymentAvailability::ABIDJAN) {
            return 'abidjan';
        }

        if ($destination === PaymentAvailability::INTERIOR) {
            return 'interior';
        }

        $country = strtoupper(trim((string) ($order->shipping_address['country'] ?? '')));

        return in_array($country, self::AFRICA, true) ? 'sous_region' : 'international';
    }

    /**
     * Next extraction of the zone at or after this moment.
     *
     * An order in before 21:00 on a scheduled day leaves that evening; after
     * that, it waits for the next scheduled day — for Abidjan, anything after
     * Thursday evening waits for Sunday evening.
     */
    public static function nextRun(string $zone, Carbon $after): Carbon
    {
        $run = $after->copy()->setTime(self::RUN_HOUR, 0);

        if ($after->gt($run)) {
            $run->addDay();
        }

        // Bounded to a week: every zone has at least one scheduled day.
        for ($i = 0; $i < 7 && ! self::isScheduled($zone, $run); $i++) {
            $run->addDay();
        }

        return $run;
    }

    /** Settings key holding the zone's last extraction time. */
    public static function lastRunKey(string $zone): string
    {
        return "shipment_sheet_last_run:{$zone}";
    }

    /**
     * Window start: the last extraction, or 24 hours back on the very first
     * run — with no marker, the whole history is not replayed.
     */
    public static function windowStart(string $zone): Carbon
    {
        $last = Setting::get(self::lastRunKey($zone));

        return $last ? Carbon::parse($last) : now()->subDay();
    }

    public static function rememberRun(string $zone, Carbon $moment): void
    {
        Setting::set(self::lastRunKey($zone), $moment->toIso8601String());
    }

    /**
     * Column that brings an order into the window.
     *
     * Abidjan is paid on delivery: the order date is what counts. Elsewhere
     * only paid orders ship, so payment decides departure — an old order paid
     * this morning goes tonight, a recent unpaid one waits. Dating by creation
     * left an order paid after its window with no extraction to pick it up.
     */
    public static function windowColumn(string $zone): string
    {
        return $zone === 'abidjan' ? 'created_at' : 'paid_at';
    }

    /**
     * Orders in the window, ready to go.
     *
     * @return Collection<int, Order>
     */
    public static function due(string $zone, Carbon $from, Carbon $to): Collection
    {
        $column = self::windowColumn($zone);

        return self::ready($zone)
            ->whereBetween($column, [$from, $to])
            ->orderBy($column)
            ->get();
    }

    /**
     * Orders older than the window that were never extracted.
     *
     * The safety net of the sliding window: an order that could not leave in
     * time drops out of the window and would never come back. It is reported
     * separately instead of being silently lost.
     *
     * Three causes, all covered here: a missing payment, a shipping fee not yet
     * settled, or — the classic miss — an order ready in time yet absent from
     * the run it belonged to.
     *
     * @return Collection<int, Order>
     */
    public static function overdue(string $zone, Carbon $from): Collection
    {
        $readyNow = self::ready($zone)
            ->whereBetween(self::windowColumn($zone), [$from, now()])
            ->pluck('id');

        return self::scoped($zone)
            ->whereNull('exported_at')
            ->where('created_at', '<', $from)
            ->whereNotIn('id', $readyNow)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Zone and status only, with no readiness condition.
     *
     * An order's zone is not a single column: `destination` separates Abidjan,
     * the interior and abroad, and abroad then splits between sous-région and
     * DHL by delivery country.
     */
    private static function scoped(string $zone): Builder
    {
        return Order::with(['items', 'user'])
            ->whereIn('status', self::TO_SHIP)
            ->where(fn (Builder $q) => match ($zone) {
                'abidjan'       => $q->where('destination', PaymentAvailability::ABIDJAN),
                'interior'      => $q->where('destination', PaymentAvailability::INTERIOR),
                'sous_region'   => $q->where('destination', PaymentAvailability::INTERNATIONAL)
                                     ->whereIn('shipping_address->country', self::AFRICA),
                'international' => $q->where('destination', PaymentAvailability::INTERNATIONAL)
                                     ->whereNotIn('shipping_address->country', self::AFRICA),
            });
    }

    /** Orders ready to leave: the zone, plus what its destination requires. */
    private static function ready(string $zone): Builder
    {
        $abroad = in_array($zone, ['sous_region', 'international'], true);

        return self::scoped($zone)
            // Already extracted, by hand or by a previous run: it is being
            // prepared, not to be sent again.
            ->whereNull('exported_at')
            // A far-away parcel refused on arrival costs twice: only paid
            // orders ship outside Abidjan.
            ->when(self::requiresPayment($zone), fn ($q) => $q->whereNotNull('paid_at'))
            // Abroad, nothing leaves until the fee is settled — customers refuse
            // an amount that has to be corrected upwards.
            ->when($abroad, fn ($q) => $q
                ->where('shipping_cost', '>', 0)
                ->where(fn ($w) => $w->whereNull('metadata->shipping_unknown')
                                     ->orWhereJsonDoesntContain('metadata->shipping_unknown', true))
            );
    }
}
