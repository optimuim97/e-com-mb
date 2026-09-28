<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Admin\Models\Setting;
use Modules\Order\Models\Order;
use Modules\Order\Services\OrderNotificationMessage;
use Modules\Order\Support\DeliveryEstimate;
use Tests\TestCase;

/**
 * Delivery date promised to the customer, on the extraction calendar.
 *
 * Cases taken from the shop's own rules:
 *   Abidjan, Tuesday 15:00           → delivered Wednesday
 *   Abidjan, Thursday 22:00 / Saturday → delivered Monday
 *   Interior, paid Monday            → ships Wednesday, arrives in a range
 *   DHL, paid Tuesday 10:00          → ships Wednesday, arrives in a range
 */
class DeliveryEstimateTest extends TestCase
{
    use RefreshDatabase;

    // Reference week: 2026-09-20 is a Sunday.
    private const MONDAY    = '2026-09-21 10:00:00';
    private const TUESDAY   = '2026-09-22 15:00:00';
    private const THURSDAY  = '2026-09-24 22:00:00';
    private const SATURDAY  = '2026-09-26 11:00:00';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(string $country, string $city, array $extra = []): Order
    {
        return Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => User::factory()->create()->id,
            'status'           => 'pending',
            'subtotal'         => 5000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 6500,
            'currency'         => 'XOF',
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => $city,
                'commune' => $city === 'Abidjan' ? 'Cocody' : null,
                'country' => $country, 'phone' => '0102030405',
            ],
        ], $extra))->refresh();
    }

    // ── Abidjan ─────────────────────────────────────────────────────────────

    public function test_abidjan_tuesday_afternoon_is_delivered_wednesday(): void
    {
        Carbon::setTestNow(self::TUESDAY);

        $eta = DeliveryEstimate::for($this->order('CI', 'Abidjan'));

        $this->assertTrue($eta['firm']);
        $this->assertFalse($eta['conditional']);
        $this->assertSame('2026-09-23', $eta['from']);
        $this->assertSame('2026-09-23', $eta['to']);
    }

    public function test_abidjan_after_thursday_run_is_delivered_monday(): void
    {
        Carbon::setTestNow(self::THURSDAY);
        $this->assertSame('2026-09-28', DeliveryEstimate::for($this->order('CI', 'Abidjan'))['from']);

        Carbon::setTestNow(self::SATURDAY);
        $this->assertSame('2026-09-28', DeliveryEstimate::for($this->order('CI', 'Abidjan'))['from']);
    }

    public function test_abidjan_does_not_wait_for_payment(): void
    {
        Carbon::setTestNow(self::TUESDAY);

        // Cash on delivery: an unpaid Abidjan order is not conditional.
        $this->assertFalse(DeliveryEstimate::for($this->order('CI', 'Abidjan'))['conditional']);
    }

    // ── Interior ────────────────────────────────────────────────────────────

    public function test_interior_paid_monday_ships_wednesday(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $eta = DeliveryEstimate::for($this->order('CI', 'Bouaké', ['paid_at' => now()]));

        $this->assertFalse($eta['firm']);
        $this->assertFalse($eta['conditional']);
        $this->assertSame('2026-09-23', $eta['ships_on']);
        // Default transit: 1 to 2 days.
        $this->assertSame('2026-09-24', $eta['from']);
        $this->assertSame('2026-09-25', $eta['to']);
    }

    public function test_unpaid_interior_order_is_conditional(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $eta = DeliveryEstimate::for($this->order('CI', 'Bouaké'));

        $this->assertTrue($eta['conditional']);
        $this->assertStringContainsString(
            'sous réserve de votre règlement',
            DeliveryEstimate::sentence($eta),
        );
    }

    // ── Abroad ──────────────────────────────────────────────────────────────

    public function test_dhl_paid_tuesday_morning_ships_wednesday(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00');

        $eta = DeliveryEstimate::for($this->order('FR', 'Paris', ['paid_at' => now()]));

        $this->assertSame('international', $eta['zone']);
        $this->assertSame('2026-09-23', $eta['ships_on']);
    }

    public function test_pending_fee_abroad_keeps_the_date_conditional(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $eta = DeliveryEstimate::for($this->order('SN', 'Dakar', [
            'paid_at'       => now(),
            'shipping_cost' => 0,
            'metadata'      => ['shipping_unknown' => true],
        ]));

        $this->assertSame('sous_region', $eta['zone']);
        $this->assertTrue($eta['conditional']);
        $this->assertStringContainsString('frais de livraison', DeliveryEstimate::sentence($eta));
    }

    // ── Once in motion ──────────────────────────────────────────────────────

    public function test_an_extracted_order_ships_the_next_morning(): void
    {
        // Extracted Tuesday evening, looked at on Wednesday morning: it left
        // this morning, it is not pushed to Thursday's run.
        Carbon::setTestNow('2026-09-23 09:00:00');

        $eta = DeliveryEstimate::for($this->order('CI', 'Bouaké', [
            'paid_at'     => '2026-09-21 10:00:00',
            'exported_at' => '2026-09-22 21:00:00',
        ]));

        $this->assertSame('2026-09-23', $eta['ships_on']);
    }

    public function test_delivered_or_cancelled_orders_promise_nothing(): void
    {
        $this->assertNull(DeliveryEstimate::for($this->order('CI', 'Abidjan', ['status' => 'delivered'])));
        $this->assertNull(DeliveryEstimate::for($this->order('CI', 'Abidjan', ['status' => 'cancelled'])));
    }

    // ── Admin settings ──────────────────────────────────────────────────────

    public function test_transit_times_come_from_the_settings(): void
    {
        Carbon::setTestNow(self::MONDAY);

        Setting::set('delivery_transit_interior_min', '2');
        Setting::set('delivery_transit_interior_max', '4');

        $eta = DeliveryEstimate::for($this->order('CI', 'Bouaké', ['paid_at' => now()]));

        $this->assertSame('2026-09-25', $eta['from']);
        $this->assertSame('2026-09-27', $eta['to']);
    }

    public function test_inconsistent_settings_fall_back_to_defaults(): void
    {
        Setting::set('delivery_transit_interior_min', '5');
        Setting::set('delivery_transit_interior_max', '2');

        $this->assertSame(DeliveryEstimate::TRANSIT_DEFAULTS['interior'], DeliveryEstimate::transit('interior'));
    }

    // ── Where the customer sees it ──────────────────────────────────────────

    public function test_the_whatsapp_message_carries_the_date(): void
    {
        Carbon::setTestNow(self::TUESDAY);

        $message = app(OrderNotificationMessage::class)->build($this->order('CI', 'Abidjan'));

        $this->assertStringContainsString('Livraison prévue le mercredi 23 septembre', $message);
    }

    public function test_the_order_api_exposes_the_estimate(): void
    {
        Carbon::setTestNow(self::TUESDAY);

        $user  = User::factory()->create();
        $order = $this->order('CI', 'Abidjan', ['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/orders/{$order->number}")
            ->assertOk()
            ->assertJsonPath('data.delivery_estimate.from', '2026-09-23')
            ->assertJsonPath('data.delivery_estimate.firm', true);
    }
}
