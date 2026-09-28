<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\Order;
use Modules\Order\Support\OrderAttention;
use Tests\TestCase;

/**
 * The « À traiter » queue: what has passed its deadline, and why.
 *
 * The point is that nothing is forgotten in silence. Each rule surfaces the
 * order on its own; the two gestures that reach a customer — cancelling an
 * unpaid order, apologising for a late parcel — stay with the agent.
 *
 * Reference week: 2026-09-21 is a Monday, 2026-09-26 a Saturday.
 */
class OrderAttentionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);

        // Mardi matin : la boutique est ouverte, les extractions de lundi soir
        // sont passées.
        Carbon::setTestNow('2026-09-22 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(array $extra = [], string $city = 'Abidjan', string $country = 'CI'): Order
    {
        $order = Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => User::factory()->create()->id,
            'status'           => 'pending',
            'subtotal'         => 10000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 11500,
            'currency'         => 'XOF',
            'payment_method'   => 'cod',
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => $city,
                'commune' => $city === 'Abidjan' ? 'Cocody' : null,
                'country' => $country, 'phone' => '0709080706',
            ],
        ], array_diff_key($extra, ['created_at' => true])));

        if (isset($extra['created_at'])) {
            $order->forceFill(['created_at' => $extra['created_at']])->saveQuietly();
        }

        return $order->refresh();
    }

    /** @return array<int, string> */
    private function codes(Order $order): array
    {
        return array_column(OrderAttention::for($order), 'code');
    }

    // ── Abidjan : à appeler ─────────────────────────────────────────────────

    public function test_an_unconfirmed_abidjan_order_past_its_run_must_be_called(): void
    {
        // Passée lundi à 15 h, jamais confirmée : l'extraction de lundi 21 h
        // est passée sans elle.
        $order = $this->order(['created_at' => '2026-09-21 15:00:00']);

        $this->assertContains(OrderAttention::TO_CALL, $this->codes($order));
    }

    public function test_an_abidjan_order_before_its_run_is_not_flagged(): void
    {
        // Passée ce matin : son extraction est ce soir, rien n'est en retard.
        $order = $this->order(['created_at' => '2026-09-22 08:30:00']);

        $this->assertSame([], $this->codes($order));
    }

    public function test_a_confirmed_abidjan_order_is_not_to_be_called(): void
    {
        $order = $this->order([
            'created_at'  => '2026-09-21 15:00:00',
            'status'      => 'processing',
            'exported_at' => '2026-09-21 21:00:00',
        ]);

        $this->assertSame([], $this->codes($order));
    }

    // ── Hors Abidjan : règlement ────────────────────────────────────────────

    public function test_an_unpaid_order_outside_abidjan_is_flagged_after_a_day(): void
    {
        $order = $this->order(['created_at' => '2026-09-21 09:00:00'], 'Bouaké');

        $this->assertContains(OrderAttention::UNPAID, $this->codes($order));
    }

    public function test_after_a_week_unpaid_the_cancellation_is_proposed(): void
    {
        $order = $this->order(['created_at' => '2026-09-14 09:00:00'], 'Bouaké');

        $codes = $this->codes($order);

        // Proposée, jamais faite : c'est l'agente qui annule.
        $this->assertContains(OrderAttention::CANCEL_SUGGESTED, $codes);
        // Proposée seulement : la commande, elle, ne bouge pas.
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_a_paid_order_outside_abidjan_is_not_chased(): void
    {
        $order = $this->order([
            'created_at'  => '2026-09-21 09:00:00',
            'paid_at'     => '2026-09-21 10:00:00',
            'exported_at' => '2026-09-21 21:00:00',
            'status'      => 'processing',
        ], 'Bouaké');

        $this->assertSame([], $this->codes($order));
    }

    // ── Frais à fixer ───────────────────────────────────────────────────────

    public function test_unset_fees_are_flagged_after_a_day_of_opening_hours(): void
    {
        $order = $this->order([
            'created_at'     => '2026-09-18 09:00:00',
            'shipping_cost'  => 0,
            'metadata'       => ['shipping_unknown' => true],
            'payment_method' => null,
        ], 'Paris', 'FR');

        $this->assertContains(OrderAttention::FEE_PENDING, $this->codes($order));
    }

    public function test_the_weekend_does_not_count_against_the_shop(): void
    {
        // Passée vendredi 20 h, regardée samedi : une heure ouvrée s'est
        // écoulée, pas vingt-quatre. Personne n'était là.
        Carbon::setTestNow('2026-09-26 11:00:00');

        $order = $this->order([
            'created_at'     => '2026-09-25 20:00:00',
            'shipping_cost'  => 0,
            'metadata'       => ['shipping_unknown' => true],
            'payment_method' => null,
        ], 'Paris', 'FR');

        $this->assertNotContains(OrderAttention::FEE_PENDING, $this->codes($order));
    }

    // ── L'oubli type ────────────────────────────────────────────────────────

    public function test_a_ready_order_absent_from_its_run_is_flagged_at_once(): void
    {
        // L'intérieur s'extrait le mardi et le jeudi soir. Payée mardi dernier
        // au matin, jamais extraite : son passage du soir est passé sans elle,
        // et aucune fenêtre ne la reprendra.
        $order = $this->order([
            'created_at' => '2026-09-15 09:00:00',
            'paid_at'    => '2026-09-15 10:00:00',
            'status'     => 'processing',
        ], 'Bouaké');

        $codes = $this->codes($order);

        $this->assertContains(OrderAttention::MISSED_EXTRACTION, $codes);
        // Sans délai de grâce : c'est déjà trop tard.
        $this->assertSame('critical', OrderAttention::for($order)[0]['level']);
    }

    public function test_an_order_blocked_by_payment_is_not_also_called_a_miss(): void
    {
        // Elle attend son règlement : le dire deux fois noierait la file.
        $order = $this->order(['created_at' => '2026-09-21 09:00:00'], 'Bouaké');

        $this->assertNotContains(OrderAttention::MISSED_EXTRACTION, $this->codes($order));
    }

    // ── Livraison en retard ─────────────────────────────────────────────────

    public function test_a_parcel_past_its_promised_date_is_flagged(): void
    {
        $order = $this->order([
            'created_at'  => '2026-09-10 09:00:00',
            'paid_at'     => '2026-09-10 10:00:00',
            'status'      => 'shipped',
            'shipped_at'  => '2026-09-11 09:30:00',
            'exported_at' => '2026-09-10 21:00:00',
        ], 'Bouaké');

        $this->assertContains(OrderAttention::LATE_DELIVERY, $this->codes($order));
    }

    public function test_a_parcel_still_within_its_window_is_left_alone(): void
    {
        $order = $this->order([
            'created_at'  => '2026-09-21 09:00:00',
            'paid_at'     => '2026-09-21 10:00:00',
            'status'      => 'shipped',
            'shipped_at'  => '2026-09-22 08:00:00',
            'exported_at' => '2026-09-21 21:00:00',
        ], 'Bouaké');

        $this->assertSame([], $this->codes($order));
    }

    // ── Hors périmètre ──────────────────────────────────────────────────────

    public function test_settled_and_archived_orders_never_appear(): void
    {
        $delivered = $this->order(['created_at' => '2026-09-01 09:00:00', 'status' => 'delivered']);
        $archived  = $this->order(['created_at' => '2026-09-01 09:00:00', 'archived_at' => now()]);

        $this->assertSame([], $this->codes($delivered));
        $this->assertSame([], $this->codes($archived));
    }

    // ── L'écran ─────────────────────────────────────────────────────────────

    public function test_the_queue_lists_the_oldest_first_with_its_reason(): void
    {
        $vieille = $this->order(['created_at' => '2026-09-14 09:00:00'], 'Bouaké');
        $recente = $this->order(['created_at' => '2026-09-21 09:00:00'], 'Bouaké');
        // Rien à signaler : elle ne doit pas encombrer la file.
        $this->order(['created_at' => '2026-09-22 08:30:00']);

        Sanctum::actingAs($this->admin);

        $reponse = $this->getJson('/api/admin/orders/attention')->assertOk();

        $this->assertSame(2, $reponse->json('total'));
        $this->assertSame($vieille->number, $reponse->json('data.0.order.number'));
        $this->assertSame($recente->number, $reponse->json('data.1.order.number'));
        $this->assertSame('critical', $reponse->json('data.0.level'));
        $this->assertSame(1, $reponse->json('counts.' . OrderAttention::CANCEL_SUGGESTED));
        $this->assertNotEmpty($reponse->json('data.0.attention.0.hint'));
    }

    public function test_the_queue_needs_the_orders_permission(): void
    {
        $livreur = User::factory()->create();
        $livreur->syncRoles(['livreur']);
        Sanctum::actingAs($livreur);

        $this->getJson('/api/admin/orders/attention')->assertForbidden();
    }
}
