<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Promo\Models\Promotion;
use Tests\TestCase;

/**
 * Administration des promotions : ce que l'écran autorise et refuse.
 */
class PromotionAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['admin']);
        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Soldes de fin d’année',
            'scope'         => Promotion::SCOPE_GLOBAL,
            'type'          => Promotion::TYPE_SEASONAL,
            'discount_type' => Promotion::DISCOUNT_PERCENT,
            'value'         => 20,
            'is_active'     => true,
        ], $overrides);
    }

    public function test_a_promotion_can_be_created(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/admin/promotions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('type', Promotion::TYPE_SEASONAL);

        $this->assertDatabaseHas('promotions', ['name' => 'Soldes de fin d’année']);
    }

    public function test_all_market_types_are_accepted(): void
    {
        Sanctum::actingAs($this->manager());

        foreach (Promotion::TYPES as $type) {
            $this->postJson('/api/admin/promotions', $this->payload([
                'name' => "Operation {$type}",
                'type' => $type,
            ]))->assertCreated();
        }

        $this->assertSame(count(Promotion::TYPES), Promotion::count());
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/admin/promotions', $this->payload(['type' => 'blackfriday']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_a_percentage_above_one_hundred_is_rejected(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/admin/promotions', $this->payload(['value' => 120]))
            ->assertStatus(422);
    }

    public function test_a_targeted_promotion_needs_at_least_one_product(): void
    {
        // Sans article, l'opération existerait sans jamais rien remiser.
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/admin/promotions', $this->payload([
            'scope'       => Promotion::SCOPE_PRODUCT,
            'product_ids' => [],
        ]))->assertStatus(422);
    }

    public function test_a_cap_only_applies_to_percentage_discounts(): void
    {
        Sanctum::actingAs($this->manager());

        $this->postJson('/api/admin/promotions', $this->payload([
            'discount_type'       => Promotion::DISCOUNT_FIXED,
            'value'               => 2000,
            'max_discount_amount' => 5000,
        ]))->assertStatus(422);
    }

    public function test_the_cap_limits_the_discount(): void
    {
        // « −30 % dans la limite de 5 000 F » : sur un article à 80 000 F,
        // la remise s'arrête à 5 000 et non 24 000.
        $promotion = Promotion::create($this->payload([
            'value'               => 30,
            'max_discount_amount' => 5000,
        ]));

        $this->assertSame(75000.0, $promotion->priceFor(80000));
    }

    public function test_switching_to_global_scope_detaches_products(): void
    {
        // Garder des rattachements sur une promotion devenue globale
        // afficherait une liste d'articles qui ne veut plus rien dire.
        $product   = Product::factory()->create();
        $promotion = Promotion::create($this->payload(['scope' => Promotion::SCOPE_PRODUCT]));
        $promotion->products()->attach($product->id);

        Sanctum::actingAs($this->manager());
        $this->putJson("/api/admin/promotions/{$promotion->id}", [
            'scope' => Promotion::SCOPE_GLOBAL,
        ])->assertOk();

        $this->assertCount(0, $promotion->fresh()->products);
    }

    public function test_the_badge_falls_back_to_the_discount(): void
    {
        $promotion = Promotion::create($this->payload(['value' => 25]));
        $this->assertSame('−25 %', $promotion->badge());

        $named = Promotion::create($this->payload([
            'name'         => 'Autre',
            'banner_label' => 'DÉSTOCKAGE',
        ]));
        $this->assertSame('DÉSTOCKAGE', $named->badge());
    }

    public function test_a_product_can_carry_its_own_discount(): void
    {
        // Une campagne annonce souvent un taux général tout en traitant
        // certaines références à part. Sans cette dérogation il fallait créer
        // une promotion par taux.
        $standard  = Product::factory()->priced(10000)->create();
        $exception = Product::factory()->priced(10000)->create();

        Sanctum::actingAs($this->manager());
        $id = $this->postJson('/api/admin/promotions', $this->payload([
            'scope'    => Promotion::SCOPE_PRODUCT,
            'value'    => 20,
            'products' => [
                ['id' => $standard->id],
                ['id' => $exception->id, 'discount_type' => 'percent', 'value' => 50],
            ],
        ]))->assertCreated()->json('id');

        $promotion = Promotion::with('products')->find($id);
        $resolver  = app(\Modules\Promo\Services\PromotionResolver::class);

        $this->assertSame(8000.0, $resolver->priceFor($standard->fresh()),
            'Sans dérogation, l’article suit le taux de l’opération.');
        $this->assertSame(5000.0, $resolver->priceFor($exception->fresh()),
            'Avec dérogation, c’est son taux propre qui s’applique.');
    }

    public function test_a_per_product_discount_cannot_exceed_one_hundred_percent(): void
    {
        $product = Product::factory()->priced(10000)->create();

        Sanctum::actingAs($this->manager());
        $this->postJson('/api/admin/promotions', $this->payload([
            'scope'    => Promotion::SCOPE_PRODUCT,
            'products' => [['id' => $product->id, 'discount_type' => 'percent', 'value' => 150]],
        ]))->assertStatus(422);
    }

    public function test_the_promotion_cap_also_bounds_a_per_product_discount(): void
    {
        // Le plafond borne ce que la campagne peut coûter : une dérogation ne
        // doit pas permettre de le contourner.
        $product = Product::factory()->priced(80000)->create();

        Sanctum::actingAs($this->manager());
        $this->postJson('/api/admin/promotions', $this->payload([
            'scope'               => Promotion::SCOPE_PRODUCT,
            'value'               => 10,
            'max_discount_amount' => 5000,
            'products'            => [['id' => $product->id, 'discount_type' => 'percent', 'value' => 50]],
        ]))->assertCreated();

        $resolver = app(\Modules\Promo\Services\PromotionResolver::class);
        $this->assertSame(75000.0, $resolver->priceFor($product->fresh()));
    }

    public function test_a_staff_member_can_read_but_not_create_promotions(): void
    {
        // Une promotion fixe les prix de vente : l'agent doit pouvoir répondre
        // à une cliente sans pouvoir lancer une opération de son propre chef.
        $staff = User::factory()->create();
        $staff->syncRoles(['staff']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/promotions')->assertOk();
        $this->postJson('/api/admin/promotions', $this->payload())->assertForbidden();
    }

    public function test_a_staff_member_cannot_bypass_the_counter_discount_cap(): void
    {
        // `promotions.edit` lève le plafond de remise au comptoir : le staff ne
        // doit pas l'avoir, sans quoi le garde-fou ne protège personne.
        $staff = User::factory()->create();
        $staff->syncRoles(['staff']);

        $this->assertFalse($staff->can('promotions.edit'));
    }
}
