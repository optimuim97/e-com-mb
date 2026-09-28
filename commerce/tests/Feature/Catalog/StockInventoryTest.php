<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

/**
 * Cycle d'inventaire : export de la feuille de comptage, puis réalignement.
 *
 * Le comportement qui compte le plus n'est pas de savoir écrire un chiffre en
 * base, c'est de ne PAS en écrire quand la case est vide : un rayon non
 * terminé ne doit jamais se traduire par une rupture de stock généralisée.
 */
class StockInventoryTest extends TestCase
{
    use RefreshDatabase;

    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fichier = storage_path('app/test-inventaire-' . uniqid() . '.csv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->fichier)) {
            unlink($this->fichier);
        }
        parent::tearDown();
    }

    public function test_l_export_liste_les_produits_avec_leur_stock(): void
    {
        $produit = Product::factory()->create(['name' => 'Eau de rose', 'stock' => 17, 'sku' => 'RB-EDR-01']);

        $this->artisan('shop:export-stock', ['--out' => $this->fichier])->assertSuccessful();

        $contenu = file_get_contents($this->fichier);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu, 'Le BOM manque : Excel FR casserait les accents.');
        // fputcsv entoure de guillemets tout champ contenant une espace.
        $this->assertStringContainsString('P' . $produit->id . ';RB-EDR-01;"Eau de rose";;17;;', $contenu);
    }

    public function test_une_case_vide_laisse_le_stock_intact(): void
    {
        $produit = Product::factory()->create(['stock' => 42]);

        $this->ecrireFeuille([
            ['P' . $produit->id, $produit->sku, $produit->name, '', 42, ''],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier])->assertSuccessful();

        $this->assertSame(42, $produit->fresh()->stock);
    }

    public function test_le_comptage_ecrase_le_stock_de_la_base(): void
    {
        $produit = Product::factory()->create(['stock' => 20]);

        $this->ecrireFeuille([
            ['P' . $produit->id, $produit->sku, $produit->name, '', 20, 13],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier])
            ->expectsConfirmation('Appliquer ces 1 correction(s) au stock ?', 'yes')
            ->assertSuccessful();

        $this->assertSame(13, $produit->fresh()->stock);
    }

    public function test_le_mode_simulation_n_ecrit_rien(): void
    {
        $produit = Product::factory()->create(['stock' => 20]);

        $this->ecrireFeuille([
            ['P' . $produit->id, $produit->sku, $produit->name, '', 20, 5],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(20, $produit->fresh()->stock);
    }

    public function test_une_reference_inconnue_est_signalee_sans_bloquer_le_reste(): void
    {
        $produit = Product::factory()->create(['stock' => 20]);

        $this->ecrireFeuille([
            ['P999999', 'SKU-FANTOME', 'Produit supprimé', '', 0, 4],
            ['P' . $produit->id, $produit->sku, $produit->name, '', 20, 8],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier])
            ->expectsConfirmation('Appliquer ces 1 correction(s) au stock ?', 'yes')
            ->assertSuccessful();

        $this->assertSame(8, $produit->fresh()->stock);
    }

    public function test_les_prix_ne_bougent_pas_sans_l_option_dediee(): void
    {
        // Un inventaire répond à « combien en ai-je ? ». Laisser la même
        // feuille redéfinir les tarifs en silence mélange deux décisions.
        $produit = Product::factory()->create(['stock' => 20, 'price' => 5000]);

        $this->ecrireFeuille([
            ['P' . $produit->id, $produit->sku, $produit->name, '', 20, 18, '9999'],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier])
            ->expectsConfirmation('Appliquer ces 1 correction(s) au stock ?', 'yes')
            ->assertSuccessful();

        $produit->refresh();
        $this->assertSame(18, $produit->stock);
        $this->assertSame('5000.00', (string) $produit->price);
    }

    public function test_l_option_prix_applique_le_tarif_de_la_feuille(): void
    {
        $produit = Product::factory()->create(['stock' => 20, 'price' => 5000]);

        // Virgule décimale : c'est ce que produit un tableur français.
        $this->ecrireFeuille([
            ['P' . $produit->id, $produit->sku, $produit->name, '', 20, 18, '7500,50'],
        ]);

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier, '--prix' => true])
            ->expectsConfirmation('Appliquer ces 1 correction(s) au stock ?', 'yes')
            ->assertSuccessful();

        $this->assertSame('7500.50', (string) $produit->fresh()->price);
    }

    public function test_un_fichier_sans_colonne_de_comptage_est_refuse(): void
    {
        file_put_contents($this->fichier, "id;reference;produit\nP1;RB-01;Eau de rose\n");

        $this->artisan('shop:import-stock', ['fichier' => $this->fichier])->assertFailed();
    }

    /**
     * Écrit une feuille au format de l'export.
     *
     * @param  array<int, array<int, string|int|null>>  $lignes
     */
    private function ecrireFeuille(array $lignes): void
    {
        $handle = fopen($this->fichier, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['id', 'reference', 'produit', 'variante', 'stock_systeme', 'stock_compte', 'prix_vente', 'cout_achat', 'seuil_alerte'], ';', '"', '');

        foreach ($lignes as $ligne) {
            fputcsv($handle, array_pad($ligne, 9, ''), ';', '"', '');
        }

        fclose($handle);
    }
}
