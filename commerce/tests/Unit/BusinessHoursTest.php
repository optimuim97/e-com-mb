<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Modules\Order\Support\BusinessHours;
use PHPUnit\Framework\TestCase;

/**
 * The shop's clock: Monday to Friday, 8:00 to 21:00.
 *
 * Reference week: 2026-09-21 is a Monday, 2026-09-26 a Saturday.
 */
class BusinessHoursTest extends TestCase
{
    public function test_opening_hours(): void
    {
        $this->assertTrue(BusinessHours::isOpen(Carbon::parse('2026-09-21 08:00')));
        $this->assertTrue(BusinessHours::isOpen(Carbon::parse('2026-09-21 20:59')));

        $this->assertFalse(BusinessHours::isOpen(Carbon::parse('2026-09-21 07:59')));
        $this->assertFalse(BusinessHours::isOpen(Carbon::parse('2026-09-21 21:00')));
        // Fermée le week-end.
        $this->assertFalse(BusinessHours::isOpen(Carbon::parse('2026-09-26 10:00')));
    }

    public function test_the_next_opening_skips_nights_and_weekends(): void
    {
        // Avant l'ouverture : le matin même.
        $this->assertSame(
            '2026-09-21 08:00',
            BusinessHours::next(Carbon::parse('2026-09-21 06:00'))->format('Y-m-d H:i'),
        );

        // Après la fermeture : le lendemain.
        $this->assertSame(
            '2026-09-22 08:00',
            BusinessHours::next(Carbon::parse('2026-09-21 22:00'))->format('Y-m-d H:i'),
        );

        // Vendredi soir et samedi renvoient au lundi.
        $this->assertSame(
            '2026-09-28 08:00',
            BusinessHours::next(Carbon::parse('2026-09-25 21:30'))->format('Y-m-d H:i'),
        );
        $this->assertSame(
            '2026-09-28 08:00',
            BusinessHours::next(Carbon::parse('2026-09-26 11:00'))->format('Y-m-d H:i'),
        );
    }

    public function test_adding_hours_stays_inside_opening_hours(): void
    {
        // Dans la journée.
        $this->assertSame(
            '2026-09-21 14:00',
            BusinessHours::add(Carbon::parse('2026-09-21 10:00'), 4)->format('Y-m-d H:i'),
        );

        // La journée fait treize heures : 24 h ouvrées débordent sur le
        // lendemain, pas sur la nuit.
        $this->assertSame(
            '2026-09-22 18:00',
            BusinessHours::add(Carbon::parse('2026-09-21 13:00'), 18)->format('Y-m-d H:i'),
        );

        // Le samedi ne compte pas : vendredi 20 h + 2 h = lundi 9 h.
        $this->assertSame(
            '2026-09-28 09:00',
            BusinessHours::add(Carbon::parse('2026-09-25 20:00'), 2)->format('Y-m-d H:i'),
        );
    }

    public function test_elapsed_hours_ignore_closing_time(): void
    {
        $this->assertSame(
            4.0,
            BusinessHours::elapsed(Carbon::parse('2026-09-21 10:00'), Carbon::parse('2026-09-21 14:00')),
        );

        // Du vendredi 20 h au lundi 9 h : une heure le vendredi, une le lundi.
        $this->assertSame(
            2.0,
            BusinessHours::elapsed(Carbon::parse('2026-09-25 20:00'), Carbon::parse('2026-09-28 09:00')),
        );

        // Un week-end entier ne compte pour rien.
        $this->assertSame(
            0.0,
            BusinessHours::elapsed(Carbon::parse('2026-09-26 09:00'), Carbon::parse('2026-09-27 18:00')),
        );
    }
}
