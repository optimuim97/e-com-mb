<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Support\DuplicateOrderCheck;
use Tests\TestCase;

/**
 * A customer about to order what she already has coming.
 *
 * It happens on a phone: the confirmation is slow, the page is reloaded, or an
 * agent re-enters on WhatsApp an order already placed on the site. Two parcels
 * go out, one comes back.
 *
 * The answer is a warning, never a refusal: confirming places the order
 * unchanged. What matters is that she is told.
 */
class DuplicateOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);

        $this->customer = User::factory()->create(['phone' => '0709080706']);
    }

    /** An order already on its way, holding the given products. */
    private function openOrder(array $products, string $status = 'pending', ?User $user = null): Order
    {
        $order = Order::create([
            'number'           => Order::generateNumber(),
            'user_id'          => ($user ?? $this->customer)->id,
            'status'           => $status,
            'subtotal'         => 10000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 11500,
            'currency'         => 'XOF',
            'shipping_address' => [
                'first_name' => 'Fatou', 'last_name' => 'Konaté',
                'city' => 'Abidjan', 'commune' => 'Cocody',
                'country' => 'CI', 'phone' => '0709080706',
            ],
        ]);

        foreach ($products as $product) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'quantity'     => 1,
                'unit_price'   => 10000,
                'total_price'  => 10000,
            ]);
        }

        return $order->refresh();
    }

    /** @param array<int, Product> $products */
    private function quickOrder(array $products, array $extra = [])
    {
        return $this->postJson('/api/quick-order', array_merge([
            'name'           => 'Fatou Konaté',
            'phone'          => '0709080706',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'items'          => array_map(
                fn (Product $p) => ['product_id' => $p->id, 'quantity' => 1],
                $products,
            ),
        ], $extra));
    }

    // ── The warning ─────────────────────────────────────────────────────────

    public function test_an_identical_order_still_on_its_way_is_flagged(): void
    {
        $cream = Product::factory()->priced(10000)->create(['name' => 'Huile de rose']);
        $this->openOrder([$cream]);

        $response = $this->quickOrder([$cream])->assertStatus(409);

        $response->assertJsonPath('requires_confirmation', true);
        // She has to recognise it: its number, where it stands, what is in it.
        $response->assertJsonPath('duplicate_order.status', 'pending');
        $this->assertSame('Huile de rose', $response->json('duplicate_order.items.0.name'));
        $this->assertNotNull($response->json('duplicate_order.number'));

        // Nothing was written while the question is pending.
        $this->assertSame(1, Order::count());
    }

    public function test_confirming_lets_the_order_through(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $this->openOrder([$cream]);

        // A customer may really order the same cream twice in one week.
        $this->quickOrder([$cream], ['confirm_duplicate' => true])->assertCreated();

        $this->assertSame(2, Order::count());
    }

    // ── What is not a duplicate ─────────────────────────────────────────────

    public function test_a_delivered_order_is_not_a_duplicate(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $this->openOrder([$cream], 'delivered');

        // Settled: ordering it again is a repeat purchase.
        $this->quickOrder([$cream])->assertCreated();
    }

    public function test_a_cancelled_order_is_not_a_duplicate(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $this->openOrder([$cream], 'cancelled');

        $this->quickOrder([$cream])->assertCreated();
    }

    public function test_a_shipped_order_still_counts(): void
    {
        // Shipped is not delivered: the parcel is on its way, a second one
        // would follow it.
        $cream = Product::factory()->priced(10000)->create();
        $this->openOrder([$cream], 'shipped');

        $this->quickOrder([$cream])->assertStatus(409);
    }

    public function test_a_different_basket_goes_straight_through(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $soap  = Product::factory()->priced(5000)->create();
        $this->openOrder([$cream]);

        $this->quickOrder([$soap])->assertCreated();
    }

    public function test_another_customer_is_not_warned(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $other = User::factory()->create(['phone' => '0102030405']);
        $this->openOrder([$cream], 'pending', $other);

        $this->quickOrder([$cream], ['phone' => '0505060708'])->assertCreated();
    }

    // ── The 90 % rule ───────────────────────────────────────────────────────

    public function test_one_item_out_of_ten_does_not_make_a_duplicate(): void
    {
        $products = Product::factory()->count(10)->priced(10000)->create();
        $this->openOrder($products->all());

        // Ordering one cream while a ten-item order is on its way is not a
        // double, even though that cream is in both.
        $this->quickOrder([$products->first()])->assertCreated();
    }

    public function test_similarity_is_measured_against_the_longer_basket(): void
    {
        $keys = fn (array $ids) => DuplicateOrderCheck::keys(
            array_map(fn ($id) => ['product_id' => $id, 'variant_id' => null], $ids),
        );

        $this->assertSame(1.0, DuplicateOrderCheck::similarity($keys([1, 2]), $keys([1, 2])));
        $this->assertSame(0.5, DuplicateOrderCheck::similarity($keys([1]), $keys([1, 2])));
        $this->assertSame(0.9, DuplicateOrderCheck::similarity(
            $keys(range(1, 9)),
            $keys(range(1, 10)),
        ));
    }

    public function test_a_variant_tells_two_lines_apart(): void
    {
        // Same product, another size: it is not the same parcel.
        $cream = Product::factory()->priced(10000)->create();

        $this->assertSame(0.0, DuplicateOrderCheck::similarity(
            DuplicateOrderCheck::keys([['product_id' => $cream->id, 'variant_id' => 1]]),
            DuplicateOrderCheck::keys([['product_id' => $cream->id, 'variant_id' => 2]]),
        ));
    }

    // ── Order of checks ─────────────────────────────────────────────────────

    public function test_an_unavailable_item_is_refused_before_the_duplicate_question(): void
    {
        // Out of stock: asking her to confirm a duplicate for an order that
        // cannot be placed anyway would cost two round trips and teach her
        // nothing. Availability speaks first.
        $sold_out = Product::factory()->priced(10000)->create([
            'stock' => 0, 'track_stock' => true, 'is_active' => true,
        ]);
        $this->openOrder([$sold_out]);

        $this->quickOrder([$sold_out])->assertStatus(422);
    }

    // ── The signed-in checkout ──────────────────────────────────────────────

    public function test_the_checkout_warns_too(): void
    {
        $cream = Product::factory()->priced(10000)->create();
        $this->openOrder([$cream]);

        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_address' => [
                    'first_name' => 'Fatou', 'address_line1' => 'Cocody',
                    'city' => 'Abidjan', 'commune' => 'Cocody',
                    'country' => 'CI', 'phone' => '0709080706',
                ],
                'payment_method' => 'cod',
                'items'          => [['product_id' => $cream->id, 'quantity' => 1]],
            ])
            ->assertStatus(409)
            ->assertJsonPath('requires_confirmation', true);
    }
}
