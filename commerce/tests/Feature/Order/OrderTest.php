<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Coupon\Models\Coupon;
use Modules\Order\Models\Order;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Les zones de livraison conditionnent le moyen de paiement :
        // sans elles, toute adresse est vue « hors zone » et le checkout
        // refuse le règlement. On reproduit donc la configuration réelle.
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->setUpUser();
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function shippingAddress(array $overrides = []): array
    {
        return array_merge([
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Cocody, Abidjan',
            'city'          => 'Abidjan',
            'commune'       => 'Cocody',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ], $overrides);
    }

    private function orderPayload(array $overrides = []): array
    {
        return array_merge([
            'shipping_address' => $this->shippingAddress(),
            'payment_method'   => 'cod',
        ], $overrides);
    }

    private function seedCart(int $qty = 1, ?int $price = null): array
    {
        $product = Product::factory()->priced($price ?? 10000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qty]);

        return [$product, $cart];
    }

    // Les adresses de test visent Cocody, tarifée 1 500 F : les totaux
    // attendus incluent donc la livraison.

    // ── Création de commande depuis le panier ──────────────────────────────

    public function test_creation_commande_depuis_panier(): void
    {
        [$product] = $this->seedCart(2, 8000);

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload())
             ->assertCreated()
             ->assertJsonStructure(['number', 'status', 'total', 'items'])
             ->assertJsonPath('status', 'pending')
             ->assertJsonPath('total', '17500.00');
    }

    public function test_la_commande_est_enregistree_en_base(): void
    {
        $this->seedCart(1, 5000);

        $response = $this->asUser()
                         ->postJson('/api/orders', $this->orderPayload())
                         ->assertCreated();

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->user->id,
            'status'  => 'pending',
            'number'  => $response->json('number'),
        ]);
    }

    public function test_le_panier_est_vide_apres_commande(): void
    {
        [$product, $cart] = $this->seedCart();

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload())
             ->assertCreated();

        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_les_items_de_commande_sont_crees(): void
    {
        [$product] = $this->seedCart(3, 6000);

        $response = $this->asUser()
                         ->postJson('/api/orders', $this->orderPayload())
                         ->assertCreated();

        $number = $response->json('number');
        $order  = Order::where('number', $number)->first();

        $this->assertCount(1, $order->items);
        $this->assertEquals(3, $order->items->first()->quantity);
        $this->assertEquals('6000.00', $order->items->first()->unit_price);
    }

    public function test_stock_decremente_apres_commande(): void
    {
        $product = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 3]);

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload())
             ->assertCreated();

        $this->assertEquals(7, $product->fresh()->stock);
    }

    // ── Commande depuis le payload (fallback panier vide) ──────────────────

    public function test_creation_commande_depuis_payload_items(): void
    {
        $product = Product::factory()->priced(12000)->create();

        $payload = $this->orderPayload([
            'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
        ]);

        $this->asUser()
             ->postJson('/api/orders', $payload)
             ->assertCreated()
             ->assertJsonPath('total', '25500.00');
    }

    public function test_commande_avec_items_payload_ignorees_quand_panier_db_non_vide(): void
    {
        // Panier DB = 1 × 10 000 CFA
        $product1 = Product::factory()->priced(10000)->create();
        $cart     = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product1->id, 'quantity' => 1]);

        // Payload items = 1 × 5 000 CFA (doit être ignoré)
        $product2 = Product::factory()->priced(5000)->create();
        $payload  = $this->orderPayload([
            'items' => [['product_id' => $product2->id, 'variant_id' => null, 'quantity' => 1]],
        ]);

        $this->asUser()
             ->postJson('/api/orders', $payload)
             ->assertCreated()
             ->assertJsonPath('total', '11500.00'); // panier DB utilisé
    }

    // ── Panier vide ────────────────────────────────────────────────────────

    public function test_panier_vide_sans_payload_items_retourne_422(): void
    {
        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload())
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Panier vide']);
    }

    // ── Validation du payload ──────────────────────────────────────────────

    public function test_adresse_incomplete_retourne_erreur_validation(): void
    {
        $this->seedCart();

        $this->asUser()
             ->postJson('/api/orders', [
                 'shipping_address' => ['first_name' => 'Fatou'],
                 'payment_method'   => 'cod',
             ])
             ->assertUnprocessable()
             // last_name est nullable (optionnel) → pas dans les erreurs attendues.
             ->assertJsonValidationErrors([
                 'shipping_address.address_line1',
                 'shipping_address.city',
                 'shipping_address.country',
             ]);
    }

    public function test_methode_paiement_invalide_retourne_erreur(): void
    {
        $this->seedCart();

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload(['payment_method' => 'bitcoin']))
             ->assertUnprocessable()
             ->assertJsonValidationErrors('payment_method');
    }

    public function test_les_moyens_de_paiement_dependent_de_la_destination(): void
    {
        // Abidjan se règle à la livraison ; l'intérieur par mobile money.
        // Proposer partout la même liste ferait accepter un règlement que la
        // logistique ne sait pas honorer.
        // « delivery » existe pour la commande rapide mais pas au checkout
        // authentifié : on s'en tient ici aux moyens réellement proposés.
        $cas = [
            ['commune' => 'Cocody', 'city' => 'Abidjan', 'method' => 'cod'],
            ['commune' => null,     'city' => 'Bouaké',  'method' => 'wave'],
            ['commune' => null,     'city' => 'Bouaké',  'method' => 'orange_money'],
        ];

        foreach ($cas as $c) {
            Cart::where('user_id', $this->user->id)->delete();
            $this->seedCart();

            $adresse = $this->shippingAddress(['city' => $c['city']]);
            $adresse['commune'] = $c['commune'];

            $this->asUser()
                 ->postJson('/api/orders', [
                     'shipping_address' => $adresse,
                     'payment_method'   => $c['method'],
                 ])
                 ->assertCreated();
        }
    }

    public function test_un_moyen_de_paiement_inadapte_a_la_destination_est_refuse(): void
    {
        $this->seedCart();

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload(['payment_method' => 'wave']))
             ->assertUnprocessable()
             ->assertJsonValidationErrors('payment_method');
    }

    /**
     * ⚠ Constat : le vocabulaire des moyens de paiement diffère selon le point
     * d'entrée. Le checkout authentifié (/api/orders) REFUSE 'delivery' et
     * 'virement', alors que /api/quick-order accepte 'delivery' et le POS
     * accepte 'cash'/'cinetpay'. Ce test verrouille le comportement actuel de
     * /api/orders et documente l'incohérence.
     */
    public function test_methodes_non_supportees_par_checkout_authentifie(): void
    {
        foreach (['delivery', 'virement', 'cash', 'cinetpay'] as $method) {
            Cart::where('user_id', $this->user->id)->delete();
            $this->seedCart();
            $this->asUser()
                 ->postJson('/api/orders', $this->orderPayload(['payment_method' => $method]))
                 ->assertUnprocessable()
                 ->assertJsonValidationErrors('payment_method');
        }
    }

    public function test_checkout_sans_nom_de_famille_reussit(): void
    {
        // Régression : last_name est nullable → l'omettre ne doit PAS provoquer
        // un 500 (ArgumentCountError dans AddressData), mais créer la commande.
        $product = Product::factory()->priced(5000)->create();

        $this->asUser()
             ->postJson('/api/orders', [
                 'shipping_address' => [
                     'first_name'    => 'Fatou',
                     'address_line1' => 'Cocody, Abidjan',
                     'city'          => 'Abidjan',
            'commune'       => 'Cocody',
                     'country'       => 'CI',
                     'phone'         => '0102030405',
                 ],
                 'payment_method' => 'cod',
                 'items'          => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
             ])
             ->assertCreated();
    }

    public function test_une_commande_internationale_aboutit_sans_reglement(): void
    {
        // Hors Côte d'Ivoire, rien ne se règle en ligne : les frais dépendent du
        // transporteur, du poids et parfois de la douane. La commande part quand
        // même et l'agent conclut — c'est le déroulé de la commande rapide, que
        // le tunnel classique refusait jusqu'ici, laissant l'international sans
        // aucun moyen de commander.
        $product = Product::factory()->priced(5000)->create();

        $this->asUser()
             ->postJson('/api/orders', [
                 'shipping_address' => $this->shippingAddress(['country' => 'OTHER']),
                 'items'            => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
             ])
             ->assertCreated()
             ->assertJsonPath('status', 'pending')
             ->assertJsonPath('payment_method', null)
             // Frais non chiffrés : la page de suivi bascule sur l'agent.
             ->assertJsonPath('shipping_fee_pending', true);
    }

    // ── Commande avec coupon ───────────────────────────────────────────────

    public function test_commande_applique_reduction_coupon(): void
    {
        Coupon::factory()->percentage(10)->create(['code' => 'ROSA10']);
        [$product, $cart] = $this->seedCart(1, 10000);
        $cart->update(['coupon_code' => 'ROSA10']);

        $this->asUser()
             ->postJson('/api/orders', $this->orderPayload())
             ->assertCreated()
             ->assertJsonPath('discount_amount', '1000.00')
             ->assertJsonPath('total', '10500.00');
    }

    // ── Consultation des commandes ─────────────────────────────────────────

    public function test_liste_commandes_utilisateur(): void
    {
        Order::factory()->count(3)->create(['user_id' => $this->user->id]);
        Order::factory()->create(); // autre utilisateur — ne doit pas apparaître

        $this->asUser()
             ->getJson('/api/orders')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_detail_commande_par_numero(): void
    {
        $order = Order::factory()->create(['user_id' => $this->user->id]);

        $this->asUser()
             ->getJson("/api/orders/{$order->number}")
             ->assertOk()
             ->assertJsonPath('data.number', $order->number)
             ->assertJsonPath('data.status', $order->status);
    }

    public function test_impossible_de_voir_commande_dun_autre_utilisateur(): void
    {
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->asUser()
             ->getJson("/api/orders/{$order->number}")
             ->assertNotFound();
    }

    public function test_commandes_inaccessibles_sans_authentification(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}
