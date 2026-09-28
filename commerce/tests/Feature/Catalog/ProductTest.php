<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    // ── Liste des produits ─────────────────────────────────────────────────

    public function test_liste_des_produits_actifs(): void
    {
        Product::factory()->count(3)->create();
        Product::factory()->inactive()->create();   // ne doit pas apparaître

        $this->getJson('/api/products')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_les_produits_inactifs_sont_exclus(): void
    {
        Product::factory()->inactive()->count(2)->create();

        $this->getJson('/api/products')
             ->assertOk()
             ->assertJsonCount(0, 'data');
    }

    public function test_liste_paginee_respecte_per_page(): void
    {
        Product::factory()->count(10)->create();

        $this->getJson('/api/products?per_page=5')
             ->assertOk()
             ->assertJsonCount(5, 'data')
             ->assertJsonPath('meta.per_page', 5);
    }

    public function test_filtre_par_featured(): void
    {
        Product::factory()->featured()->count(2)->create();
        Product::factory()->count(3)->create();

        $this->getJson('/api/products?featured=1')
             ->assertOk()
             ->assertJsonCount(2, 'data');
    }

    public function test_filtre_par_prix_min(): void
    {
        Product::factory()->priced(5000)->create();
        Product::factory()->priced(20000)->create();

        $this->getJson('/api/products?min_price=10000')
             ->assertOk()
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.price', '20000.00');
    }

    public function test_filtre_par_prix_max(): void
    {
        Product::factory()->priced(5000)->create();
        Product::factory()->priced(20000)->create();

        $this->getJson('/api/products?max_price=10000')
             ->assertOk()
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.price', '5000.00');
    }

    public function test_recherche_par_nom(): void
    {
        Product::factory()->create(['name' => 'Huile de Rose Pure', 'slug' => 'huile-de-rose-pure']);
        Product::factory()->create(['name' => 'Crème Hydratante', 'slug' => 'creme-hydratante']);

        $this->getJson('/api/products?search=rose')
             ->assertOk()
             ->assertJsonCount(1, 'data')
             ->assertJsonPath('data.0.name', 'Huile de Rose Pure');
    }

    public function test_tri_par_prix_croissant(): void
    {
        Product::factory()->priced(15000)->create(['name' => 'B', 'slug' => 'b']);
        Product::factory()->priced(5000)->create(['name' => 'A',  'slug' => 'a']);

        $data = $this->getJson('/api/products?sort=price_asc')
                     ->assertOk()
                     ->json('data');

        $this->assertEquals('5000.00', $data[0]['price']);
        $this->assertEquals('15000.00', $data[1]['price']);
    }

    public function test_tri_par_prix_decroissant(): void
    {
        Product::factory()->priced(5000)->create(['name' => 'A',  'slug' => 'a']);
        Product::factory()->priced(15000)->create(['name' => 'B', 'slug' => 'b']);

        $data = $this->getJson('/api/products?sort=price_desc')
                     ->assertOk()
                     ->json('data');

        $this->assertEquals('15000.00', $data[0]['price']);
    }

    // ── Détail produit ─────────────────────────────────────────────────────

    public function test_affichage_produit_par_slug(): void
    {
        $product = Product::factory()->create(['name' => 'Sérum Éclat', 'slug' => 'serum-eclat']);

        $this->getJson("/api/products/{$product->slug}")
             ->assertOk()
             ->assertJsonPath('data.slug', 'serum-eclat')
             ->assertJsonPath('data.name', 'Sérum Éclat')
             ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'price', 'stock']]);
    }

    public function test_produit_inexistant_retourne_404(): void
    {
        $this->getJson('/api/products/produit-qui-nexiste-pas')
             ->assertNotFound();
    }

    public function test_produit_inactif_retourne_404(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/products/{$product->slug}")
             ->assertNotFound();
    }

    public function test_la_reponse_inclut_les_champs_attendus(): void
    {
        $product = Product::factory()->create([
            'price'         => 12000,
            'compare_price' => 15000,
        ]);

        $this->getJson("/api/products/{$product->slug}")
             ->assertOk()
             ->assertJsonStructure(['data' => [
                 'id', 'name', 'slug', 'price', 'compare_price',
                 'discount_percent', 'stock', 'sku', 'is_active',
             ]])
             ->assertJsonPath('data.discount_percent', 20); // (1 - 12000/15000)*100 = 20%
    }

    public function test_stock_epuise_visible_dans_la_liste(): void
    {
        Product::factory()->outOfStock()->create();

        $data = $this->getJson('/api/products')->assertOk()->json('data');

        $this->assertEquals(0, $data[0]['stock']);
    }
}
