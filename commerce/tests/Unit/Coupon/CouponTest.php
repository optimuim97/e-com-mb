<?php

namespace Tests\Unit\Coupon;

use Modules\Coupon\Models\Coupon;
use Tests\TestCase;

class CouponTest extends TestCase
{
    private function coupon(array $attrs): Coupon
    {
        return new Coupon(array_merge([
            'type'           => 'percentage',
            'value'          => 10,
            'is_active'      => true,
            'minimum_amount' => null,
            'usage_limit'    => null,
            'used_count'     => 0,
            'starts_at'      => null,
            'expires_at'     => null,
        ], $attrs));
    }

    // ── isValid ────────────────────────────────────────────────────────────

    public function test_coupon_actif_est_valide(): void
    {
        $this->assertTrue($this->coupon(['is_active' => true])->isValid(10000));
    }

    public function test_coupon_inactif_invalide(): void
    {
        $this->assertFalse($this->coupon(['is_active' => false])->isValid(10000));
    }

    public function test_coupon_expire_invalide(): void
    {
        $c = $this->coupon(['expires_at' => now()->subHour()]);
        $this->assertFalse($c->isValid(10000));
    }

    public function test_coupon_pas_encore_commence_invalide(): void
    {
        $c = $this->coupon(['starts_at' => now()->addHour()]);
        $this->assertFalse($c->isValid(10000));
    }

    public function test_coupon_epuise_invalide(): void
    {
        $c = $this->coupon(['usage_limit' => 5, 'used_count' => 5]);
        $this->assertFalse($c->isValid(10000));
    }

    public function test_coupon_montant_minimum_non_atteint_invalide(): void
    {
        $c = $this->coupon(['minimum_amount' => 20000]);
        $this->assertFalse($c->isValid(10000));
    }

    public function test_coupon_montant_minimum_atteint_valide(): void
    {
        $c = $this->coupon(['minimum_amount' => 10000]);
        $this->assertTrue($c->isValid(10000));
    }

    // ── computeDiscount ────────────────────────────────────────────────────

    public function test_remise_pourcentage(): void
    {
        $c = $this->coupon(['type' => 'percentage', 'value' => 20]);
        $this->assertEquals(2000.0, $c->computeDiscount(10000));
    }

    public function test_remise_fixe(): void
    {
        $c = $this->coupon(['type' => 'fixed', 'value' => 3000]);
        $this->assertEquals(3000.0, $c->computeDiscount(10000));
    }

    public function test_remise_fixe_plafonnee_au_total_panier(): void
    {
        $c = $this->coupon(['type' => 'fixed', 'value' => 15000]);
        $this->assertEquals(10000.0, $c->computeDiscount(10000));
    }

    public function test_livraison_gratuite_remise_zero(): void
    {
        $c = $this->coupon(['type' => 'free_shipping']);
        $this->assertEquals(0.0, $c->computeDiscount(10000));
    }

    public function test_type_inconnu_retourne_zero(): void
    {
        $c = $this->coupon(['type' => 'magic_discount']);
        $this->assertEquals(0.0, $c->computeDiscount(10000));
    }
}
