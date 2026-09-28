<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\HomeStatsCache;
use Modules\Promo\Models\Promotion;
use Tests\TestCase;

/**
 * La page d'accueil doit refléter l'administration sans attendre.
 *
 * Sa charge utile est mise en cache cinq minutes — neuf requêtes lourdes à
 * chaque chargement — mais rien ne la périmait : une opération créée en
 * administration n'apparaissait qu'au bout de cinq minutes, ou après un
 * `php artisan cache:clear` lancé à la main sur le serveur.
 */
class HomeStatsCacheTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function campagne(): ?array
    {
        return $this->getJson('/api/home/stats')->assertOk()->json('promo_campaign');
    }

    public function test_une_operation_creee_apparait_immediatement(): void
    {
        $product = Product::factory()->create(['price' => 5000]);

        // Première lecture : l'accueil est désormais en cache, sans opération.
        $this->assertNull($this->campagne());

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/promotions', [
            'name'          => 'Rosa Days',
            'scope'         => 'product',
            'type'          => 'standard',
            'discount_type' => 'percent',
            'value'         => 15,
            'is_active'     => true,
            'products'      => [['id' => $product->id]],
        ])->assertCreated();

        $campagne = $this->campagne();

        $this->assertNotNull($campagne, "L'opération est restée invisible : le cache d'accueil n'a pas été vidé.");
        $this->assertSame('Rosa Days', $campagne['name']);
        $this->assertSame(15, $campagne['max_percent']);
    }

    public function test_une_operation_supprimee_disparait_immediatement(): void
    {
        $product   = Product::factory()->create(['price' => 5000]);
        $promotion = Promotion::create([
            'name' => 'Rosa Days', 'scope' => 'product', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => 15, 'is_active' => true,
        ]);
        $promotion->products()->attach($product->id);

        $this->assertNotNull($this->campagne());

        Sanctum::actingAs($this->admin());
        $this->deleteJson("/api/admin/promotions/{$promotion->id}")->assertOk();

        $this->assertNull($this->campagne());
    }

    public function test_changer_les_articles_dune_operation_perime_laccueil(): void
    {
        $premier = Product::factory()->create(['price' => 5000]);
        $second  = Product::factory()->create(['price' => 8000]);

        $promotion = Promotion::create([
            'name' => 'Rosa Days', 'scope' => 'product', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => 15, 'is_active' => true,
        ]);
        $promotion->products()->attach($premier->id);

        $this->assertSame(1, $this->campagne()['products_count']);

        // Le pivot n'émet aucun événement de modèle : c'est le cas que les
        // écouteurs sur Promotion et Product ne couvrent pas.
        Sanctum::actingAs($this->admin());
        $this->putJson("/api/admin/promotions/{$promotion->id}", [
            'products' => [['id' => $premier->id], ['id' => $second->id]],
        ])->assertOk();

        $this->assertSame(2, $this->campagne()['products_count']);
    }

    public function test_le_cache_sert_bien_entre_deux_modifications(): void
    {
        Product::factory()->create(['price' => 5000]);

        $this->campagne();

        // Écriture directe en base : aucun événement de modèle, donc aucune
        // purge. Si la valeur en cache n'était pas resservie, le cache ne
        // servirait à rien.
        Promotion::query()->insert([
            'name' => 'Rosa Days', 'scope' => 'global', 'type' => 'standard',
            'discount_type' => 'percent', 'value' => 15, 'is_active' => true,
            'priority' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertNull($this->campagne());

        HomeStatsCache::forget();

        $this->assertNotNull($this->campagne());
    }
}
