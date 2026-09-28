<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Modules\Promo\Models\Promotion;
use Tests\TestCase;

/**
 * Attribution d'une vente à l'opération qui l'a permise.
 *
 * La ligne de commande ne retenait que le prix payé : « combien les Rosa Days
 * ont-elles vendu » était sans réponse, et le restera pour l'historique — le
 * prix effectif dépend d'opérations qui ont pu expirer ou changer depuis.
 * L'information s'écrit donc à la vente, ou jamais.
 */
class PromotionAttributionTest extends TestCase
{
    use RefreshDatabase;

    private function articleRemise(int $prix = 5000, float $taux = 20): array
    {
        $product = Product::factory()->create(['price' => $prix, 'stock' => 50]);

        $promotion = Promotion::create([
            'name' => 'Rosa Days', 'scope' => 'product', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => $taux, 'is_active' => true,
        ]);
        $promotion->products()->attach($product->id);

        return [$product, $promotion];
    }

    private function commander(Product $product, int $qte = 2): Order
    {
        $cliente = User::factory()->create(['phone' => '0102030405']);
        $cart    = Cart::create(['user_id' => $cliente->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qte]);

        Sanctum::actingAs($cliente);

        $reponse = $this->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné',
                'address_line1' => 'Rue du marché', 'city' => 'Abidjan',
                'commune' => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertCreated();

        return Order::where('number', $reponse->json('number'))->firstOrFail();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
    }

    public function test_la_ligne_retient_loperation_et_la_remise(): void
    {
        [$product, $promotion] = $this->articleRemise();

        $ligne = $this->commander($product, 2)->items->first();

        $this->assertSame($promotion->id, $ligne->promotion_id);
        $this->assertSame('4000.00', $ligne->unit_price);
        // 1 000 de remise par article, sur deux articles.
        $this->assertSame('2000.00', $ligne->discount_amount);
    }

    public function test_sans_operation_la_ligne_nattribue_rien(): void
    {
        $product = Product::factory()->create(['price' => 5000, 'stock' => 50]);

        $ligne = $this->commander($product)->items->first();

        $this->assertNull($ligne->promotion_id);
        $this->assertSame('0.00', $ligne->discount_amount);
    }

    public function test_une_vente_flash_plus_avantageuse_nest_pas_mise_au_credit_de_loperation(): void
    {
        // Promotion à -20 % (4 000), vente flash à 3 000 : c'est la flash qui
        // fixe le prix. Attribuer la vente à l'opération lui prêterait une
        // remise qu'elle n'a pas accordée.
        [$product] = $this->articleRemise(5000, 20);
        $product->update([
            'is_flash_sale' => true,
            'flash_price'   => 3000,
            'flash_starts_at' => now()->subHour(),
            'flash_ends_at'   => now()->addDay(),
        ]);

        $ligne = $this->commander($product)->items->first();

        $this->assertSame('3000.00', $ligne->unit_price);
        $this->assertNull($ligne->promotion_id);
        // La remise reste comptée : elle est réelle, elle n'est simplement
        // imputable à aucune opération.
        $this->assertSame('4000.00', $ligne->discount_amount);
    }

    public function test_loperation_lemporte_si_elle_est_la_plus_avantageuse(): void
    {
        [$product, $promotion] = $this->articleRemise(5000, 40);   // 3 000
        $product->update([
            'is_flash_sale' => true,
            'flash_price'   => 4500,
            'flash_starts_at' => now()->subHour(),
            'flash_ends_at'   => now()->addDay(),
        ]);

        $ligne = $this->commander($product)->items->first();

        $this->assertSame('3000.00', $ligne->unit_price);
        $this->assertSame($promotion->id, $ligne->promotion_id);
    }

    public function test_le_parcours_invite_attribue_aussi(): void
    {
        [$product, $promotion] = $this->articleRemise();

        $reponse = $this->postJson('/api/quick-order', [
            'name' => 'Awa Konaté', 'phone' => '0102030406', 'commune' => 'Cocody',
            'payment_method' => 'cod',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $ligne = Order::where('number', $reponse->json('number'))->firstOrFail()->items->first();

        $this->assertSame($promotion->id, $ligne->promotion_id);
        $this->assertSame('1000.00', $ligne->discount_amount);
    }

    public function test_la_caisse_attribue_aussi(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        [$product, $promotion] = $this->articleRemise();

        Sanctum::actingAs($admin);
        $reponse = $this->postJson('/api/admin/pos/orders', [
            'customer' => ['name' => 'Client Comptoir', 'phone' => '0700000001'],
            'items'    => [['product_id' => $product->id, 'quantity' => 3]],
            'payment_status' => 'paid', 'payment_method' => 'cash',
        ])->assertCreated();

        $ligne = Order::where('number', $reponse->json('number'))->firstOrFail()->items->first();

        $this->assertSame($promotion->id, $ligne->promotion_id);
        $this->assertSame('3000.00', $ligne->discount_amount);
    }

    public function test_supprimer_une_operation_ne_detruit_pas_lhistorique(): void
    {
        [$product, $promotion] = $this->articleRemise();
        $commande = $this->commander($product, 1);

        $promotion->delete();

        $ligne = $commande->fresh('items')->items->first();

        // La ligne perd son rattachement, elle garde son montant : une vente
        // passée ne disparaît pas parce qu'on archive l'opération.
        $this->assertNull($ligne->promotion_id);
        $this->assertSame('4000.00', $ligne->unit_price);
        $this->assertSame('1000.00', $ligne->discount_amount);
    }
}
