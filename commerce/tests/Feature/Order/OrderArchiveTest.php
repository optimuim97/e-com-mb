<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Archiving orders from the list, by ticking them.
 *
 * Hiding, never deleting: the working list stops filling up with settled
 * orders, and nothing leaves the database. An order still owed to a customer
 * is refused — archiving it would be the surest way to forget it, which is
 * precisely what this screen fights.
 */
class OrderArchiveTest extends TestCase
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
    }

    private function order(string $status, array $extra = []): Order
    {
        return Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => User::factory()->create()->id,
            'status'           => $status,
            'subtotal'         => 10000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 11500,
            'currency'         => 'XOF',
            'paid_at'          => in_array($status, ['delivered'], true) ? now() : null,
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => 'Abidjan', 'commune' => 'Cocody',
                'country' => 'CI', 'phone' => '0709080706',
            ],
        ], $extra))->refresh();
    }

    private function archive(array $orders)
    {
        return $this->postJson('/api/admin/orders/bulk-archive', [
            'order_ids' => collect($orders)->pluck('id')->all(),
        ]);
    }

    // ── What may be archived ────────────────────────────────────────────────

    public function test_settled_orders_are_archived(): void
    {
        $delivered = $this->order('delivered');
        $cancelled = $this->order('cancelled');
        $refunded  = $this->order('refunded');

        Sanctum::actingAs($this->admin);

        $this->archive([$delivered, $cancelled, $refunded])
            ->assertOk()
            ->assertJsonCount(3, 'updated')
            ->assertJsonCount(0, 'rejected');

        $this->assertNotNull($delivered->fresh()->archived_at);
        $this->assertNotNull($cancelled->fresh()->archived_at);
        $this->assertNotNull($refunded->fresh()->archived_at);
    }

    public function test_an_order_still_in_progress_is_refused(): void
    {
        $pending = $this->order('pending');

        Sanctum::actingAs($this->admin);

        // Hiding it would be the surest way to forget it.
        $response = $this->archive([$pending])->assertOk();

        $this->assertSame([], $response->json('updated'));
        $this->assertSame($pending->number, $response->json('rejected.0.number'));
        $this->assertFalse($response->json('rejected.0.forceable'));
        $this->assertNull($pending->fresh()->archived_at);
    }

    public function test_a_delivered_but_unpaid_order_is_refused(): void
    {
        // Cash on delivery not collected: the money is still owed, and an
        // archived order is one nobody chases.
        $unpaid = $this->order('delivered', ['paid_at' => null]);

        Sanctum::actingAs($this->admin);

        $this->archive([$unpaid])->assertOk()->assertJsonCount(1, 'rejected');
        $this->assertNull($unpaid->fresh()->archived_at);
    }

    public function test_a_delivered_order_with_unset_fees_is_refused(): void
    {
        $noFee = $this->order('delivered', [
            'shipping_cost' => 0,
            'metadata'      => ['shipping_unknown' => true],
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => 'Paris',
                'country' => 'FR', 'phone' => '+33612345678',
            ],
        ]);

        Sanctum::actingAs($this->admin);

        $this->archive([$noFee])->assertOk()->assertJsonCount(1, 'rejected');
    }

    public function test_archiving_twice_changes_nothing(): void
    {
        $order = $this->order('delivered');

        Sanctum::actingAs($this->admin);
        $this->archive([$order])->assertOk();
        $moment = $order->fresh()->archived_at;

        // Already archived: nothing to do, and counting it as rejected would
        // suggest a problem.
        $this->archive([$order])->assertOk()->assertJsonCount(0, 'rejected');
        $this->assertEquals($moment, $order->fresh()->archived_at);
    }

    // ── The list ────────────────────────────────────────────────────────────

    public function test_archived_orders_leave_the_working_list(): void
    {
        $visible  = $this->order('delivered');
        $archived = $this->order('delivered', ['archived_at' => now()]);

        Sanctum::actingAs($this->admin);

        $numbers = collect($this->getJson('/api/admin/orders')->assertOk()->json('data'))
            ->pluck('number');

        $this->assertTrue($numbers->contains($visible->number));
        $this->assertFalse($numbers->contains($archived->number));
    }

    public function test_they_are_found_again_through_the_archived_tab(): void
    {
        $visible  = $this->order('delivered');
        $archived = $this->order('delivered', ['archived_at' => now()]);

        Sanctum::actingAs($this->admin);

        $numbers = collect($this->getJson('/api/admin/orders?archived=1')->assertOk()->json('data'))
            ->pluck('number');

        $this->assertTrue($numbers->contains($archived->number));
        $this->assertFalse($numbers->contains($visible->number));
    }

    public function test_nothing_is_deleted(): void
    {
        $order = $this->order('delivered');

        Sanctum::actingAs($this->admin);
        $this->archive([$order])->assertOk();

        // Still whole in the database — an order is an accounting record.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    // ── Coming back ─────────────────────────────────────────────────────────

    public function test_an_order_can_be_put_back_in_the_list(): void
    {
        $order = $this->order('delivered', ['archived_at' => now()]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-unarchive', ['order_ids' => [$order->id]])
            ->assertOk()
            ->assertJsonCount(1, 'updated');

        $this->assertNull($order->fresh()->archived_at);
    }

    // ── Permissions ─────────────────────────────────────────────────────────

    public function test_a_courier_cannot_archive(): void
    {
        $order = $this->order('delivered');

        // Le livreur pointe ses tournées : il ne range pas la liste.
        $livreur = User::factory()->create();
        $livreur->syncRoles(['livreur']);
        Sanctum::actingAs($livreur);

        $this->archive([$order])->assertForbidden();
    }
}
