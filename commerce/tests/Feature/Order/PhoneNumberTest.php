<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Order\Support\PhoneNumber;
use Tests\TestCase;

/**
 * Phone numbers, checked because the shop calls them.
 *
 * The quick order is a public endpoint and used to take any string: a parcel
 * whose number is wrong comes back. Strict for Côte d'Ivoire, where the plan is
 * certain; abroad only obvious nonsense is refused, since our rules there are
 * approximations and must never block a paying customer.
 */
class PhoneNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
    }

    // ── Côte d'Ivoire ───────────────────────────────────────────────────────

    public static function ivorianNumbers(): array
    {
        return [
            'mobile Orange'        => ['0709080706', true],
            'mobile MTN'           => ['0509080706', true],
            'mobile Moov'          => ['0109080706', true],
            'fixe Abidjan'         => ['2722334455', true],
            'espaces'              => ['07 09 08 07 06', true],
            'indicatif'            => ['+225 07 09 08 07 06', true],
            'indicatif sans plus'  => ['00225 0709080706', true],
            'ancien format 8'      => ['09080706', false],
            'neuf chiffres'        => ['070908070', false],
            'onze chiffres'        => ['07090807061', false],
            'lettres'              => ['abcdefghij', false],
            'vide de chiffres'     => ['-- --', false],
        ];
    }

    /** @dataProvider ivorianNumbers */
    public function test_ivorian_numbers_are_checked_against_the_ten_digit_plan(string $value, bool $expected): void
    {
        $this->assertSame($expected, PhoneNumber::isValid($value, 'CI'), $value);
    }

    public function test_an_ivorian_number_is_refused_at_the_quick_order(): void
    {
        $product = Product::factory()->priced(10000)->create();

        $this->postJson('/api/quick-order', [
            'name'           => 'Fatou Konaté',
            'phone'          => '09080706',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_a_valid_ivorian_number_goes_through(): void
    {
        $product = Product::factory()->priced(10000)->create();

        $this->postJson('/api/quick-order', [
            'name'           => 'Fatou Konaté',
            'phone'          => '07 09 08 07 06',
            'commune'        => 'Cocody',
            'payment_method' => 'cod',
            'items'          => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();
    }

    // ── Abroad ──────────────────────────────────────────────────────────────

    public function test_foreign_numbers_are_checked_on_length_only(): void
    {
        // France: nine national digits.
        $this->assertTrue(PhoneNumber::isValid('+33 6 12 34 56 78', 'FR'));
        $this->assertTrue(PhoneNumber::isValid('612345678', 'FR'));
        $this->assertFalse(PhoneNumber::isValid('61234', 'FR'));

        // Senegal, Mali: shorter plans, and a ten-digit Ivorian rule would
        // have refused them outright.
        $this->assertTrue(PhoneNumber::isValid('771234567', 'SN'));
        $this->assertTrue(PhoneNumber::isValid('65432100', 'ML'));
    }

    public function test_an_unlisted_country_is_not_blocked_by_our_guesswork(): void
    {
        // No rule for it: only the outer bounds apply. Refusing would block a
        // country we simply never listed.
        $this->assertTrue(PhoneNumber::isValid('+91 98765 43210', 'IN'));
        $this->assertFalse(PhoneNumber::isValid('+91 987', 'IN'));
    }

    public function test_a_short_foreign_number_is_refused_but_a_dubious_one_passes(): void
    {
        $product = Product::factory()->priced(10000)->create();

        $order = fn (string $phone) => $this->postJson('/api/quick-order', [
            'name'    => 'Fatou Konaté',
            'phone'   => $phone,
            'commune' => 'Paris',
            'city'    => 'Paris',
            'country' => 'FR',
            'items'   => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        // Cannot be a number at all → refused.
        $order('12')->assertStatus(422)->assertJsonValidationErrors('phone');

        // Wrong length for France, but plausible: a customer abroad is warned
        // by the form, never blocked by our approximations.
        $order('12345678')->assertCreated();
    }

    // ── The signed-in checkout ──────────────────────────────────────────────

    public function test_the_checkout_refuses_a_malformed_ivorian_number(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->priced(10000)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/orders', [
                'shipping_address' => [
                    'first_name' => 'Fatou', 'address_line1' => 'Cocody',
                    'city' => 'Abidjan', 'commune' => 'Cocody',
                    'country' => 'CI', 'phone' => '0708',
                ],
                'payment_method' => 'cod',
                'items'          => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address.phone');
    }
}
