<?php

namespace Tests\Feature\Wishlist;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Wishlist\Models\Wishlist;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUser();
    }

    // ── Sécurité ───────────────────────────────────────────────────────────

    public function test_wishlist_inaccessible_sans_authentification(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
    }

    public function test_toggle_inaccessible_sans_authentification(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertUnauthorized();
    }

    // ── Consulter ──────────────────────────────────────────────────────────

    public function test_wishlist_vide_par_defaut(): void
    {
        $this->asUser()
             ->getJson('/api/wishlist')
             ->assertOk()
             ->assertJsonPath('count', 0)
             ->assertJsonCount(0, 'items')
             ->assertJsonCount(0, 'product_ids');
    }

    public function test_wishlist_retourne_les_produits_ajoutes(): void
    {
        $products = Product::factory()->count(3)->create();
        foreach ($products as $p) {
            Wishlist::factory()->create(['user_id' => $this->user->id, 'product_id' => $p->id]);
        }

        $this->asUser()
             ->getJson('/api/wishlist')
             ->assertOk()
             ->assertJsonPath('count', 3)
             ->assertJsonCount(3, 'items')
             ->assertJsonCount(3, 'product_ids');
    }

    public function test_wishlist_isole_par_utilisateur(): void
    {
        $other   = User::factory()->create();
        $product = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        // Mon utilisateur ne doit voir aucun favori
        $this->asUser()
             ->getJson('/api/wishlist')
             ->assertOk()
             ->assertJsonPath('count', 0);
    }

    public function test_la_reponse_inclut_les_champs_produit(): void
    {
        $product = Product::factory()->create([
            'price'         => 12000,
            'compare_price' => 15000,
        ]);
        Wishlist::factory()->create(['user_id' => $this->user->id, 'product_id' => $product->id]);

        $item = $this->asUser()
                     ->getJson('/api/wishlist')
                     ->assertOk()
                     ->json('items.0');

        $this->assertEquals($product->id, $item['product_id']);
        $this->assertEquals($product->name, $item['product_name']);
        $this->assertEquals($product->slug, $item['product_slug']);
        $this->assertEquals('12000.00', $item['product_price']);
        $this->assertEquals(20, $item['discount_percent']); // (1 - 12000/15000)*100
    }

    // ── Toggle (ajout) ─────────────────────────────────────────────────────

    public function test_toggle_ajoute_produit_si_absent(): void
    {
        $product = Product::factory()->create();

        $this->asUser()
             ->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertOk()
             ->assertJsonPath('wishlisted', true)
             ->assertJsonPath('product_id', $product->id)
             ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('wishlists', [
            'user_id'    => $this->user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_toggle_retire_produit_si_present(): void
    {
        $product = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $this->user->id, 'product_id' => $product->id]);

        $this->asUser()
             ->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertOk()
             ->assertJsonPath('wishlisted', false)
             ->assertJsonPath('count', 0);

        $this->assertDatabaseMissing('wishlists', [
            'user_id'    => $this->user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_double_toggle_remet_a_letat_initial(): void
    {
        $product = Product::factory()->create();

        $this->asUser()->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertJsonPath('wishlisted', true);

        $this->asUser()->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertJsonPath('wishlisted', false);

        $this->assertDatabaseMissing('wishlists', [
            'user_id'    => $this->user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_toggle_produit_inexistant_retourne_erreur(): void
    {
        $this->asUser()
             ->postJson('/api/wishlist/toggle', ['product_id' => 9999])
             ->assertUnprocessable()
             ->assertJsonValidationErrors('product_id');
    }

    public function test_le_compteur_se_met_a_jour(): void
    {
        $products = Product::factory()->count(3)->create();

        foreach ($products as $i => $p) {
            $this->asUser()
                 ->postJson('/api/wishlist/toggle', ['product_id' => $p->id])
                 ->assertJsonPath('count', $i + 1);
        }
    }

    public function test_toggle_isole_par_utilisateur(): void
    {
        $other   = User::factory()->create();
        $product = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        // Mon toggle doit ajouter (pas retirer celui d'un autre)
        $this->asUser()
             ->postJson('/api/wishlist/toggle', ['product_id' => $product->id])
             ->assertJsonPath('wishlisted', true);

        $this->assertDatabaseHas('wishlists', ['user_id' => $other->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('wishlists', ['user_id' => $this->user->id, 'product_id' => $product->id]);
    }

    // ── Suppression directe ────────────────────────────────────────────────

    public function test_suppression_directe_par_product_id(): void
    {
        $product = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $this->user->id, 'product_id' => $product->id]);

        $this->asUser()
             ->deleteJson("/api/wishlist/{$product->id}")
             ->assertOk()
             ->assertJsonPath('product_id', $product->id)
             ->assertJsonPath('count', 0);

        $this->assertDatabaseMissing('wishlists', [
            'user_id'    => $this->user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_suppression_produit_absent_ne_plante_pas(): void
    {
        // Supprimer un produit pas en wishlist = opération silencieuse
        $this->asUser()
             ->deleteJson('/api/wishlist/9999')
             ->assertOk();
    }

    public function test_suppression_isole_par_utilisateur(): void
    {
        $other   = User::factory()->create();
        $product = Product::factory()->create();
        Wishlist::factory()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        $this->asUser()
             ->deleteJson("/api/wishlist/{$product->id}")
             ->assertOk();

        // Le favori de l'autre utilisateur est intact
        $this->assertDatabaseHas('wishlists', [
            'user_id'    => $other->id,
            'product_id' => $product->id,
        ]);
    }
}
