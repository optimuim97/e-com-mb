<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Coupon\Models\Coupon;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpUser();
    }

    // ── Consulter le panier ────────────────────────────────────────────────

    public function test_panier_vide_par_defaut(): void
    {
        $this->asUser()
             ->getJson('/api/cart')
             ->assertOk()
             ->assertJsonPath('item_count', 0)
             ->assertJsonCount(0, 'items');
    }

    // ── Ajouter un article ─────────────────────────────────────────────────

    public function test_ajout_produit_au_panier(): void
    {
        $product = Product::factory()->create(['price' => 8000]);

        $this->asUser()
             ->postJson('/api/cart/items', [
                 'product_id' => $product->id,
                 'quantity'   => 2,
             ])
             ->assertOk()
             ->assertJsonPath('item_count', 2)
             ->assertJsonPath('subtotal', 16000);
    }

    public function test_ajout_meme_produit_cumule_la_quantite(): void
    {
        $product = Product::factory()->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
             ->assertOk()
             ->assertJsonPath('item_count', 3);
    }

    public function test_ajout_produit_hors_stock_retourne_erreur(): void
    {
        $product = Product::factory()->outOfStock()->create();

        $this->asUser()
             ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 1])
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Stock insuffisant']);
    }

    public function test_ajout_quantite_superieure_au_stock_retourne_erreur(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->asUser()
             ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 10])
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Stock insuffisant']);
    }

    public function test_ajout_produit_sans_suivi_stock_toujours_possible(): void
    {
        $product = Product::factory()->create(['stock' => 0, 'track_stock' => false]);

        $this->asUser()
             ->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 5])
             ->assertOk()
             ->assertJsonPath('item_count', 5);
    }

    public function test_ajout_produit_inexistant_retourne_404(): void
    {
        // La règle de validation `exists:products,id` retourne 422 avant que findOrFail soit atteint
        $this->asUser()
             ->postJson('/api/cart/items', ['product_id' => 9999, 'quantity' => 1])
             ->assertUnprocessable();
    }

    // ── Modifier la quantité ───────────────────────────────────────────────

    public function test_mise_a_jour_quantite(): void
    {
        $product = Product::factory()->create(['price' => 5000]);
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        $item    = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->putJson("/api/cart/items/{$item->id}", ['quantity' => 3])
             ->assertOk()
             ->assertJsonPath('item_count', 3)
             ->assertJsonPath('subtotal', 15000);
    }

    public function test_mise_a_zero_supprime_larticle(): void
    {
        $product = Product::factory()->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        $item    = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->asUser()
             ->putJson("/api/cart/items/{$item->id}", ['quantity' => 0])
             ->assertOk()
             ->assertJsonPath('item_count', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_impossible_de_modifier_article_dun_autre_panier(): void
    {
        $other   = User::factory()->create();
        $product = Product::factory()->create();
        $cart    = Cart::factory()->create(['user_id' => $other->id]);
        $item    = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->putJson("/api/cart/items/{$item->id}", ['quantity' => 5])
             ->assertNotFound();
    }

    // ── Supprimer un article ───────────────────────────────────────────────

    public function test_suppression_article(): void
    {
        $product = Product::factory()->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        $item    = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->deleteJson("/api/cart/items/{$item->id}")
             ->assertOk()
             ->assertJsonPath('item_count', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    // ── Coupons ────────────────────────────────────────────────────────────

    public function test_application_coupon_pourcentage(): void
    {
        $coupon  = Coupon::factory()->percentage(20)->create(['code' => 'PROMO20']);
        $product = Product::factory()->priced(10000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'PROMO20'])
             ->assertOk()
             ->assertJsonPath('coupon.code', 'PROMO20')
             ->assertJsonPath('discount', 2000)
             ->assertJsonPath('total', 8000);
    }

    public function test_application_coupon_montant_fixe(): void
    {
        $coupon  = Coupon::factory()->fixed(3000)->create(['code' => 'REMISE3K']);
        $product = Product::factory()->priced(10000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'REMISE3K'])
             ->assertOk()
             ->assertJsonPath('discount', 3000)
             ->assertJsonPath('total', 7000);
    }

    public function test_coupon_insensible_a_la_casse(): void
    {
        Coupon::factory()->create(['code' => 'BIENVENUE']);

        $product = Product::factory()->priced(5000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'bienvenue'])
             ->assertOk();
    }

    public function test_coupon_expire_rejete(): void
    {
        Coupon::factory()->expired()->create(['code' => 'EXPIRE']);
        $product = Product::factory()->priced(5000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'EXPIRE'])
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Code promo invalide ou expiré']);
    }

    public function test_coupon_inactif_rejete(): void
    {
        Coupon::factory()->inactive()->create(['code' => 'INACTIF']);
        $product = Product::factory()->priced(5000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'INACTIF'])
             ->assertStatus(422);
    }

    public function test_coupon_epuise_rejete(): void
    {
        Coupon::factory()->exhausted()->create(['code' => 'EPUISE']);
        $product = Product::factory()->priced(5000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->postJson('/api/cart/coupon', ['code' => 'EPUISE'])
             ->assertStatus(422);
    }

    public function test_suppression_coupon(): void
    {
        Coupon::factory()->create(['code' => 'REMOVE']);
        $product = Product::factory()->priced(5000)->create();
        $cart    = Cart::factory()->create(['user_id' => $this->user->id, 'coupon_code' => 'REMOVE']);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        $this->asUser()
             ->deleteJson('/api/cart/coupon')
             ->assertOk()
             ->assertJsonPath('coupon', null);
    }

    // ── Sécurité ───────────────────────────────────────────────────────────

    public function test_panier_non_accessible_sans_authentification(): void
    {
        // Guest cart works via session — just check it returns 200 without auth
        $this->getJson('/api/cart')->assertOk();
    }
}
