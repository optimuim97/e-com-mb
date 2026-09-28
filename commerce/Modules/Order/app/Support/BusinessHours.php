<?php

namespace Modules\Order\Support;

use Illuminate\Support\Carbon;

/**
 * The shop's own clock: Monday to Friday, 8:00 to 21:00.
 *
 * Every deadline in OrderAttention counts in opening hours, not in calendar
 * hours. A Saturday order must not be "late" on Sunday morning — nobody was
 * there to handle it, and an alert nobody could have avoided teaches the shop
 * to ignore alerts.
 *
 * The app runs on UTC and Abidjan is on GMT+0 all year, so the server's clock
 * is the shop's clock, with no conversion.
 */
final class BusinessHours
{
    public const OPENS_AT  = 8;
    public const CLOSES_AT = 21;

    public static function isOpen(?Carbon $at = null): bool
    {
        $at = $at ?? now();

        return ! $at->isWeekend()
            && $at->hour >= self::OPENS_AT
            && $at->hour < self::CLOSES_AT;
    }

    /** The given moment if the shop is open, otherwise the next opening. */
    public static function next(Carbon $from): Carbon
    {
        $cursor = $from->copy();

        if (! $cursor->isWeekend() && $cursor->hour < self::OPENS_AT) {
            return $cursor->setTime(self::OPENS_AT, 0);
        }

        if (self::isOpen($cursor)) {
            return $cursor;
        }

        // Closed for the day, or the weekend: next working morning.
        $cursor->addDay()->setTime(self::OPENS_AT, 0);

        while ($cursor->isWeekend()) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /** The moment reached after that many opening hours. */
    public static function add(Carbon $from, float $hours): Carbon
    {
        $remaining = (int) round($hours * 60);
        $cursor    = self::next($from->copy());

        while ($remaining > 0) {
            $closing   = $cursor->copy()->setTime(self::CLOSES_AT, 0);
            $available = (int) $cursor->diffInMinutes($closing, false);

            if ($available >= $remaining) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= max(0, $available);
            $cursor = self::next($closing->addMinute());
        }

        return $cursor;
    }

    /** Opening hours elapsed between two moments. */
    public static function elapsed(Carbon $from, ?Carbon $to = null): float
    {
        $to     = ($to ?? now())->copy();
        $cursor = $from->copy();
        $minutes = 0;

        if ($cursor->gte($to)) {
            return 0.0;
        }

        while (true) {
            $cursor = self::next($cursor);

            if ($cursor->gte($to)) {
                break;
            }

            $closing = min($cursor->copy()->setTime(self::CLOSES_AT, 0), $to);
            $minutes += (int) $cursor->diffInMinutes($closing, false);
            $cursor   = $closing->copy()->addMinute();
        }

        return round($minutes / 60, 2);
    }
}
