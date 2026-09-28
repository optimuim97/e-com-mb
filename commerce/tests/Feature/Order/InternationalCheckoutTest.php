<?php

namespace Tests\Feature\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Le tunnel classique suit la commande rapide à l'international.
 *
 * Hors de Côte d'Ivoire, aucun règlement ne se choisit : les frais dépendent du
 * transporteur, du poids et parfois de la douane. La commande rapide l'a
 * toujours admis — elle vide le moyen de paiement et laisse passer la commande,
 * qu'un agent conclut ensuite. Le checkout, lui, refusait l'international quoi
 * qu'il arrive : une cliente hors du pays n'avait aucun moyen d'aboutir depuis
 * son panier.
 *
 * Ce test vérifie que les deux chemins produisent désormais la même commande.
 */
class InternationalCheckoutTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUser();
    }

    private function adresseInternationale(array $overrides = []): array
    {
        return array_merge([
            'first_name'    => 'Aminata',
            'last_name'     => 'Traoré',
            'address_line1' => '12 rue de la Paix',
            'city'          => 'Paris',
            'country'       => 'FR',
            'phone'         => '0102030405',
        ], $overrides);
    }

    private function panier(int $prix = 10000, int $qte = 2): Product
    {
        $product = Product::factory()->priced($prix)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qte]);

        return $product;
    }

    // ── Checkout ────────────────────────────────────────────────────────────

    public function test_la_commande_internationale_aboutit_depuis_le_panier(): void
    {
        $this->panier(10000, 2);

        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseInternationale()])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            // Frais à 0 pour l'instant : le total ne couvre que les articles.
            ->assertJsonPath('total', '20000.00')
            ->json('number');

        $order = Order::where('number', $numero)->firstOrFail();

        $this->assertSame('international', $order->destination);
        $this->assertNull($order->payment_method);
        // Aucune ligne de règlement : il n'y a rien à encaisser tant que le
        // montant n'est pas arrêté.
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_un_moyen_de_paiement_envoye_est_ignore(): void
    {
        // Même geste que la commande rapide : on vide le champ plutôt que de
        // refuser la commande. Un formulaire qui aurait gardé « wave » d'un
        // choix précédent ne doit pas faire échouer la commande.
        $this->panier();

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresseInternationale(),
                'payment_method'   => 'wave',
            ])
            ->assertCreated()
            ->json('number');

        $this->assertNull(Order::where('number', $numero)->value('payment_method'));
    }

    public function test_les_frais_restent_a_fixer_par_un_agent(): void
    {
        $this->panier();

        // C'est ce drapeau que la page de suivi lit pour enchaîner sur WhatsApp,
        // exactement comme la commande rapide après confirmation.
        $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseInternationale()])
            ->assertCreated()
            ->assertJsonPath('shipping_fee_pending', true);
    }

    // ── Parité avec la commande rapide ──────────────────────────────────────

    public function test_les_deux_tunnels_produisent_la_meme_commande(): void
    {
        $product = $this->panier(10000, 1);

        $checkout = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresseInternationale()])
            ->assertCreated()
            ->json();

        $rapide = $this->postJson('/api/quick-order', [
            'name'     => 'Aminata Traoré',
            'phone'    => '0709080706',
            'commune'  => 'International',
            'city'     => 'Paris',
            'country'  => 'FR',
            'items'    => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('number');

        $depuisRapide = Order::where('number', $rapide)->firstOrFail();

        $this->assertSame('international', $checkout['destination'] ?? null);
        $this->assertSame('international', $depuisRapide->destination);
        $this->assertNull($checkout['payment_method']);
        $this->assertNull($depuisRapide->payment_method);
    }

    // ── Le relâchement ne déborde pas ───────────────────────────────────────

    public function test_en_cote_divoire_le_moyen_de_paiement_reste_obligatoire(): void
    {
        \Modules\Delivery\Models\DeliveryZone::create([
            'group'   => 'Grand Abidjan',
            'name'    => 'Cocody',
            'country' => 'CI',
            'cities'  => ['Cocody'],
            'price'   => 1500,
            'active'  => true,
        ]);

        $this->panier();

        $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->adresseInternationale([
                    'city'    => 'Cocody',
                    'commune' => 'Cocody',
                    'country' => 'CI',
                ]),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }
}
