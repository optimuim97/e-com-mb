<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

/**
 * Rangement du catalogue.
 *
 * Deux règles, dans cet ordre :
 *
 *  1. Un article épuisé ferme la marche. Il reste visible — le retirer ferait
 *     disparaître la référence des recherches et des liens déjà partagés —
 *     mais la cliente ne doit pas tomber dessus en premier.
 *
 *  2. Le reste suit le rang fixé par la boutique en glisser-déposer.
 */
class ProductOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function produit(string $nom, int $stock, int $rang, bool $suivi = true): Product
    {
        return Product::factory()->priced(10000)->create([
            'name'        => $nom,
            'stock'       => $stock,
            'track_stock' => $suivi,
            'position'    => $rang,
            'is_active'   => true,
        ]);
    }

    /** Noms des produits de la vitrine, dans l'ordre rendu. */
    private function vitrine(array $parametres = []): array
    {
        return collect($this->getJson('/api/products?' . http_build_query($parametres))->assertOk()->json('data'))
            ->pluck('name')
            ->all();
    }

    // ── Les épuisés ferment la marche ──────────────────────────────────────

    public function test_un_article_epuise_passe_en_fin_de_liste(): void
    {
        $this->produit('Epuise', 0, 1);
        $this->produit('Dispo A', 5, 2);
        $this->produit('Dispo B', 5, 3);

        // Malgré son rang 1, l'épuisé passe derrière.
        $this->assertSame(['Dispo A', 'Dispo B', 'Epuise'], $this->vitrine());
    }

    public function test_les_epuises_restent_visibles(): void
    {
        $this->produit('Epuise', 0, 1);

        $this->assertContains('Epuise', $this->vitrine());
    }

    public function test_le_stock_non_suivi_nest_jamais_relegue(): void
    {
        // `track_stock` à faux : le stock affiché ne veut rien dire, l'article
        // est toujours commandable.
        $this->produit('Sans suivi', 0, 1, suivi: false);
        $this->produit('Dispo', 5, 2);

        $this->assertSame(['Sans suivi', 'Dispo'], $this->vitrine());
    }

    public function test_les_epuises_ferment_aussi_la_marche_au_tri_par_prix(): void
    {
        Product::factory()->priced(1000)->create([
            'name' => 'Epuise pas cher', 'stock' => 0, 'track_stock' => true, 'is_active' => true,
        ]);
        Product::factory()->priced(9000)->create([
            'name' => 'Dispo cher', 'stock' => 5, 'track_stock' => true, 'is_active' => true,
        ]);

        // Trier par prix croissant remonterait l'épuisé en tête : la cliente
        // cliquerait sur ce qu'elle ne peut pas acheter.
        $this->assertSame(
            ['Dispo cher', 'Epuise pas cher'],
            $this->vitrine(['sort' => 'price_asc']),
        );
    }

    // ── Rang fixé par la boutique ──────────────────────────────────────────

    public function test_la_vitrine_suit_le_rang_choisi(): void
    {
        $this->produit('Troisieme', 5, 3);
        $this->produit('Premier', 5, 1);
        $this->produit('Deuxieme', 5, 2);

        $this->assertSame(['Premier', 'Deuxieme', 'Troisieme'], $this->vitrine());
    }

    public function test_le_glisser_deposer_enregistre_le_nouvel_ordre(): void
    {
        $a = $this->produit('A', 5, 1);
        $b = $this->produit('B', 5, 2);
        $c = $this->produit('C', 5, 3);

        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/products/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk();

        $this->assertSame(['C', 'A', 'B'], $this->vitrine());
    }

    public function test_le_glisser_deposer_fonctionne_sur_un_catalogue_jamais_range(): void
    {
        // Tous les rangs a zero : c'est l'etat d'une boutique qui n'a encore
        // rien deplace. Redistribuer les rangs existants reecrivait zero
        // partout — l'appel repondait 200 sans que rien ne bouge.
        $a = $this->produit('A', 5, 0);
        $b = $this->produit('B', 5, 0);
        $c = $this->produit('C', 5, 0);

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/admin/products/reorder', ['ids' => [$c->id, $b->id, $a->id]])->assertOk();

        $this->assertSame(['C', 'B', 'A'], $this->vitrine());

        // Et les rangs sont bien distincts, sans quoi le prochain deplacement
        // retomberait dans le meme piege.
        $rangs = [$a->fresh()->position, $b->fresh()->position, $c->fresh()->position];
        $this->assertCount(3, array_unique($rangs), 'Rangs encore indistincts : ' . implode(',', $rangs));
    }

    public function test_reordonner_une_page_ne_deplace_pas_le_reste_du_catalogue(): void
    {
        $a = $this->produit('A', 5, 10);
        $b = $this->produit('B', 5, 11);
        $autre = $this->produit('Ailleurs', 5, 50);

        Sanctum::actingAs($this->admin());
        $this->putJson('/api/admin/products/reorder', ['ids' => [$b->id, $a->id]])->assertOk();

        // Les deux échangent leurs rangs ; le produit d'une autre page garde
        // le sien et reste derrière.
        $this->assertSame(10, $b->fresh()->position);
        $this->assertSame(11, $a->fresh()->position);
        $this->assertSame(50, $autre->fresh()->position);
    }

    public function test_le_reordonnancement_refuse_un_identifiant_inconnu(): void
    {
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/admin/products/reorder', ['ids' => [999999]])
            ->assertStatus(422);
    }

    public function test_le_reordonnancement_est_reserve_a_ladministration(): void
    {
        $produit = $this->produit('A', 5, 1);

        $this->putJson('/api/admin/products/reorder', ['ids' => [$produit->id]])
            ->assertStatus(401);
    }
}
