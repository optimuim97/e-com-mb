<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Stock réservé et stock physique.
 *
 * Le compteur est décrémenté à la CRÉATION de la commande, pas à sa
 * confirmation : il est donc déjà net de tout ce qui est vendu. Devant son
 * rayon, la boutique a besoin de l'autre chiffre — ce qui devrait s'y trouver,
 * commandes non expédiées comprises.
 *
 *     stock physique = compteur + réservé
 */
class StockReservationTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpUser();
    }

    private function adresse(): array
    {
        return [
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Cocody, Abidjan',
            'city'          => 'Abidjan',
            'commune'       => 'Cocody',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ];
    }

    private function commander(Product $produit, int $qte): Order
    {
        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => $qte]);

        $numero = $this->asUser()
            ->postJson('/api/orders', ['shipping_address' => $this->adresse(), 'payment_method' => 'cod'])
            ->assertCreated()
            ->json('number');

        return Order::where('number', $numero)->firstOrFail();
    }

    private function ligneAdmin(Product $produit): array
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $lignes = collect(
            $this->getJson('/api/admin/products')->assertOk()->json('data')
        )->keyBy('id');

        return $lignes[$produit->id];
    }

    // ── Le constat ─────────────────────────────────────────────────────────

    public function test_le_stock_part_des_la_creation_pas_a_la_confirmation(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'price' => 5000]);

        $commande = $this->commander($produit, 3);

        // Commande encore « en attente », stock déjà décompté.
        $this->assertSame('pending', $commande->status);
        $this->assertSame(7, (int) $produit->fresh()->stock);
    }

    public function test_confirmer_une_commande_ne_change_pas_le_compteur(): void
    {
        $produit  = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $commande = $this->commander($produit, 3);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'confirmed'])->assertOk();

        $this->assertSame(7, (int) $produit->fresh()->stock);
    }

    // ── Ce que la boutique voit ────────────────────────────────────────────

    public function test_le_reserve_correspond_aux_commandes_non_expediees(): void
    {
        $produit = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $this->commander($produit, 3);

        $ligne = $this->ligneAdmin($produit);

        $this->assertSame(7,  $ligne['stock']);
        $this->assertSame(3,  $ligne['reserved']);
        $this->assertSame(10, $ligne['physical_stock']);
    }

    public function test_une_commande_expediee_cesse_dimmobiliser_du_stock(): void
    {
        $produit  = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $commande = $this->commander($produit, 3);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/orders/{$commande->id}", ['status' => 'shipped'])->assertOk();

        $ligne = $this->ligneAdmin($produit);

        // Le colis est parti : plus rien à trouver en rayon au-delà du compteur.
        $this->assertSame(7, $ligne['stock']);
        $this->assertSame(0, $ligne['reserved']);
        $this->assertSame(7, $ligne['physical_stock']);
    }

    public function test_une_commande_annulee_nimmobilise_plus_rien(): void
    {
        $produit  = Product::factory()->create(['stock' => 10, 'price' => 5000]);
        $commande = $this->commander($produit, 3);

        $this->asUser()->postJson("/api/orders/{$commande->number}/cancel")->assertOk();

        $ligne = $this->ligneAdmin($produit);

        // Le stock est revenu : le compteur suffit.
        $this->assertSame(10, $ligne['stock']);
        $this->assertSame(0,  $ligne['reserved']);
        $this->assertSame(10, $ligne['physical_stock']);
    }

    public function test_un_produit_jamais_commande_affiche_un_reserve_nul(): void
    {
        $produit = Product::factory()->create(['stock' => 10]);

        $ligne = $this->ligneAdmin($produit);

        $this->assertSame(0,  $ligne['reserved']);
        $this->assertSame(10, $ligne['physical_stock']);
    }
}
