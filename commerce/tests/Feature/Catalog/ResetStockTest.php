<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\StockMovement;
use Tests\TestCase;

/**
 * Remise à zéro du registre avant la mise en service.
 *
 * Ce qui compte ici tient au registre, pas au compteur : après la commande,
 * l'historique ne doit plus contenir le bruit de la recette, mais il ne doit
 * pas non plus être vide. Un registre qui commence à rien ne permet pas de
 * vérifier le premier inventaire — on ne saurait pas si les 18 pots en rayon
 * sont ceux qu'on avait comptés ou le résultat d'une saisie oubliée.
 */
class ResetStockTest extends TestCase
{
    use RefreshDatabase;

    private function produit(int $stock): Product
    {
        return Product::factory()->create(['stock' => $stock, 'track_stock' => true]);
    }

    private function mouvementDeTest(Product $produit): StockMovement
    {
        return StockMovement::create([
            'product_id'   => $produit->id,
            'type'         => StockMovement::TYPE_SALE,
            'quantity'     => -2,
            'stock_before' => $produit->stock + 2,
            'stock_after'  => $produit->stock,
            'reason'       => 'Vente de recette',
        ]);
    }

    public function test_le_bruit_de_la_recette_disparait(): void
    {
        $produit = $this->produit(18);
        $this->mouvementDeTest($produit);
        $this->mouvementDeTest($produit);

        $this->artisan('shop:reset-stock --force')->assertSuccessful();

        $this->assertSame(0, StockMovement::where('type', StockMovement::TYPE_SALE)->count());
    }

    /** Le registre repart d'une ligne qui dit d'où l'on part, pas de rien. */
    public function test_une_ligne_douverture_est_posee_par_article(): void
    {
        $a = $this->produit(18);
        $b = $this->produit(5);
        $this->mouvementDeTest($a);

        $this->artisan('shop:reset-stock --force')->assertSuccessful();

        $ouvertures = StockMovement::where('type', StockMovement::TYPE_IMPORT)->get();

        $this->assertCount(2, $ouvertures);

        $ouvertureA = $ouvertures->firstWhere('product_id', $a->id);
        $this->assertSame(18, $ouvertureA->quantity);
        $this->assertSame(0,  $ouvertureA->stock_before);
        $this->assertSame(18, $ouvertureA->stock_after);
        $this->assertStringContainsString("Stock d'ouverture", $ouvertureA->reason);

        $this->assertSame(5, $ouvertures->firstWhere('product_id', $b->id)->quantity);
    }

    /** Sans `--zero`, les compteurs ne bougent pas : on décrit, on ne modifie pas. */
    public function test_les_compteurs_restent_intacts_par_defaut(): void
    {
        $produit = $this->produit(18);

        $this->artisan('shop:reset-stock --force')->assertSuccessful();

        $this->assertSame(18, $produit->fresh()->stock);
    }

    /** `--zero` prépare un comptage complet : tout repart de rien. */
    public function test_zero_remet_les_compteurs_a_zero(): void
    {
        $produit = $this->produit(18);

        $this->artisan('shop:reset-stock --force --zero')->assertSuccessful();

        $this->assertSame(0, $produit->fresh()->stock);

        $ouverture = StockMovement::where('product_id', $produit->id)->first();
        $this->assertSame(0, $ouverture->quantity);
        $this->assertSame(0, $ouverture->stock_after);
    }

    public function test_la_simulation_ne_touche_a_rien(): void
    {
        $produit = $this->produit(18);
        $this->mouvementDeTest($produit);

        $this->artisan('shop:reset-stock --dry-run')->assertSuccessful();

        $this->assertSame(1, StockMovement::count());
        $this->assertSame(18, $produit->fresh()->stock);
    }

    /** Un inventaire non attribué ne se conteste pas : l'opérateur est journalisé. */
    public function test_louverture_porte_son_operateur(): void
    {
        $mouna = User::factory()->create(['email' => 'mouna@boutique.test']);
        $this->produit(18);

        $this->artisan('shop:reset-stock --force --user=mouna@boutique.test')
            ->assertSuccessful();

        $this->assertSame($mouna->id, StockMovement::first()->user_id);
    }

    public function test_un_operateur_inconnu_arrete_la_commande(): void
    {
        $produit = $this->produit(18);
        $this->mouvementDeTest($produit);

        $this->artisan('shop:reset-stock --force --user=personne@nulle.part')
            ->assertFailed();

        // Et rien n'a été touché.
        $this->assertSame(1, StockMovement::count());
    }

    /** Un produit sans suivi de stock n'a pas de compteur à ouvrir. */
    public function test_les_produits_sans_suivi_sont_ignores(): void
    {
        Product::factory()->create(['stock' => 0, 'track_stock' => false]);
        $suivi = $this->produit(7);

        $this->artisan('shop:reset-stock --force')->assertSuccessful();

        $this->assertSame(1, StockMovement::count());
        $this->assertSame($suivi->id, StockMovement::first()->product_id);
    }
}
