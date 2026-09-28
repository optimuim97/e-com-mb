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
use Modules\Catalog\Models\WeeklyStockAllocation;
use Modules\Catalog\Services\OnlineAvailability;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Tests\TestCase;

/**
 * Ce que la boutique affiche d'un article : ce qu'elle accepte de vendre.
 *
 * La fiche montrait le stock physique alors que la commande était plafonnée par
 * l'allocation de la semaine. La cliente voyait « 40 en stock » et se faisait
 * refuser au paiement. Ce qui est vérifié ici : l'affichage et le refus disent
 * désormais la même chose, et le refus arrive au plus tôt.
 */
class OnlineAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
    }

    private function article(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true, 'track_stock' => true, 'stock' => 40, 'has_variants' => false,
        ], $attrs));
    }

    private function allouer(Product $product, int $quantite): void
    {
        WeeklyStockAllocation::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'week_start' => now()->startOfWeek()->toDateString(),
            'quantity'   => $quantite,
        ]);
    }

    private function vendre(Product $product, int $qte): void
    {
        $order = Order::factory()->create(['status' => 'processing', 'user_id' => null]);
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'quantity' => $qte,
            'unit_price' => 5000, 'total_price' => 5000 * $qte,
        ]);
    }

    private function fiche(Product $product): array
    {
        return $this->getJson("/api/products/{$product->slug}")->assertOk()->json('data');
    }

    // ── La fiche ───────────────────────────────────────────────────────────

    public function test_la_fiche_affiche_le_vendable_et_non_le_physique(): void
    {
        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);
        $this->vendre($product, 2);

        $fiche = $this->fiche($product);

        // 40 en réserve, 5 alloués, 2 vendus : la cliente peut en acheter 3.
        $this->assertSame(3, $fiche['stock']);
        $this->assertSame('available', $fiche['availability']);
    }

    public function test_quota_atteint_nest_pas_une_rupture(): void
    {
        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);
        $this->vendre($product, 5);

        $fiche = $this->fiche($product);

        // La marchandise existe : « de retour lundi », pas « épuisé ».
        $this->assertSame(0, $fiche['stock']);
        $this->assertSame('quota_reached', $fiche['availability']);
    }

    public function test_la_rupture_prime_sur_le_quota(): void
    {
        $product = $this->article(['stock' => 0]);
        $this->allouer($product, 5);

        // Réserve vide : annoncer un retour lundi serait une promesse intenable.
        $this->assertSame('out_of_stock', $this->fiche($product)['availability']);
    }

    public function test_sans_allocation_la_fiche_montre_la_reserve(): void
    {
        $product = $this->article(['stock' => 12]);

        $fiche = $this->fiche($product);

        // Un lundi oublié ne ferme pas la boutique.
        $this->assertSame(12, $fiche['stock']);
        $this->assertSame('available', $fiche['availability']);
    }

    public function test_un_article_non_suivi_se_vend_meme_a_zero(): void
    {
        $product = $this->article(['track_stock' => false, 'stock' => 0]);

        $fiche = $this->fiche($product);

        // Le front décidait « épuisé » sur `stock === 0` : un article non suivi
        // passait pour indisponible alors qu'il se commande.
        $this->assertSame('available', $fiche['availability']);
        $this->assertNull($fiche['stock']);
    }

    public function test_le_catalogue_porte_la_meme_disponibilite(): void
    {
        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);
        $this->vendre($product, 5);

        $ligne = collect($this->getJson('/api/products')->assertOk()->json('data'))
            ->firstWhere('id', $product->id);

        $this->assertSame('quota_reached', $ligne['availability']);
    }

    public function test_le_back_office_garde_le_stock_physique(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);

        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);
        $this->vendre($product, 5);

        $fiche = $this->getJson("/api/admin/products/{$product->id}")->assertOk()->json('data');

        // La gérante voit ce qui est en rayon, et l'écart avec le vendable.
        $this->assertSame(40, $fiche['stock']);
        $this->assertSame(0, $fiche['sellable_stock']);
    }

    public function test_laccueil_en_cache_montre_la_disponibilite_du_moment(): void
    {
        $product = $this->article(['stock' => 40, 'is_new' => true]);
        $this->allouer($product, 5);

        // Première lecture : la page d'accueil est mise en cache.
        $avant = collect($this->getJson('/api/home/stats')->assertOk()->json('new_collection'))
            ->firstWhere('id', $product->id);
        $this->assertSame('available', $avant['availability']);

        // Le quota s'épuise ensuite, sans rien qui périme le cache.
        $this->vendre($product, 5);

        // Frontière entre deux requêtes. En production, chaque requête repart
        // d'une application neuve ; dans un test, la même instance sert les
        // deux appels, et la lecture mémorisée par la première survivrait.
        $this->app->forgetScopedInstances();

        $apres = collect($this->getJson('/api/home/stats')->assertOk()->json('new_collection'))
            ->firstWhere('id', $product->id);

        // Figée cinq minutes, la page annoncerait des pots déjà partis.
        $this->assertSame('quota_reached', $apres['availability']);
    }

    public function test_deux_requetes_quel_que_soit_le_nombre_darticles(): void
    {
        $articles = collect(range(1, 8))->map(fn () => $this->article());
        $articles->each(fn (Product $p) => $this->allouer($p, 3));

        $availability = app(OnlineAvailability::class);

        \DB::enableQueryLog();
        $articles->each(fn (Product $p) => $availability->for($p));
        $requetes = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Allocations + vendus, une fois : pas une paire par fiche.
        $this->assertLessThanOrEqual(2, $requetes);
    }

    // ── Le refus au plus tôt ───────────────────────────────────────────────

    public function test_lajout_au_panier_refuse_au_dela_du_quota(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 3);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk();

        // Deux déjà au panier, deux de plus : c'est sur la fiche que la cliente
        // doit l'apprendre, pas après avoir saisi son adresse.
        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', "« {$product->name} » : il n'en reste que 3 à la vente en ligne cette semaine (demandé : 4).");
    }

    /**
     * La commande depuis le panier écrit ses lignes avant de contrôler le
     * quota. Sans précaution, elle se compte elle-même comme déjà vendue et se
     * refuse : une allocation de 5 interdisait un panier de 5.
     */
    public function test_la_commande_depuis_le_panier_ne_se_compte_pas_deux_fois(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 5]);

        $this->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné', 'address_line1' => 'Rue du marché',
                'city' => 'Abidjan', 'commune' => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertCreated();
    }

    public function test_les_declinaisons_dun_meme_article_se_cumulent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 3);

        // Deux lignes du même article : chacune passe seule sous le plafond,
        // leur somme non.
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'variant_id' => null]);

        $this->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné', 'address_line1' => 'Rue du marché',
                'city' => 'Abidjan', 'commune' => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertStatus(422)
          ->assertJsonPath('message', "« {$product->name} » : il n'en reste que 3 à la vente en ligne cette semaine (demandé : 4).");
    }

    public function test_la_commande_depuis_le_panier_respecte_le_quota(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $this->article(['stock' => 40]);
        $this->allouer($product, 5);
        $this->vendre($product, 4);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);

        $this->postJson('/api/orders', [
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné', 'address_line1' => 'Rue du marché',
                'city' => 'Abidjan', 'commune' => 'Cocody', 'country' => 'CI', 'phone' => '0102030405',
            ],
            'payment_method' => 'cod',
        ])->assertStatus(422)
          ->assertJsonPath('message', "« {$product->name} » : il n'en reste que 1 à la vente en ligne cette semaine (demandé : 2).");
    }
}
