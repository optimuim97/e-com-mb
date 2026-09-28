<?php

namespace Tests\Feature\Customer;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reprise du carnet clientes.
 *
 * Le risque n'est pas de rater une création, c'est d'en faire deux : une
 * cliente en double, ce sont deux historiques d'achat qui ne se rejoindront
 * plus jamais.
 */
class ImportCustomersTest extends TestCase
{
    use RefreshDatabase;

    private string $fichier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->fichier = storage_path('app/test-clientes-' . uniqid() . '.csv');
    }

    protected function tearDown(): void
    {
        if (is_file($this->fichier)) {
            unlink($this->fichier);
        }
        parent::tearDown();
    }

    public function test_les_clientes_sont_creees_avec_leur_adresse(): void
    {
        $this->ecrireCarnet([
            ['Awa Koffi', '0707849883', 'awa@example.com', 'Cocody', 'Deux Plateaux', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])
            ->expectsConfirmation('Créer ces 1 fiche(s) cliente ?', 'yes')
            ->assertSuccessful();

        $cliente = User::firstWhere('phone', '0707849883');

        $this->assertNotNull($cliente);
        $this->assertSame('Awa Koffi', $cliente->name);
        $this->assertSame('awa@example.com', $cliente->email);
        $this->assertTrue($cliente->hasRole('customer'));
        $this->assertDatabaseHas('addresses', [
            'user_id'       => $cliente->id,
            'address_line1' => 'Deux Plateaux',
            'state'         => 'Cocody',
            'country'       => 'CI',
        ]);
    }

    public function test_un_numero_deja_connu_ne_cree_pas_de_seconde_fiche(): void
    {
        // Même personne, trois écritures différentes du même numéro.
        User::factory()->create(['name' => 'Awa K.', 'phone' => '07 07 84 98 83']);

        $this->ecrireCarnet([
            ['Awa Koffi', '+225 0707849883', '', 'Cocody', '', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])->assertSuccessful();

        $this->assertSame(1, User::where('name', 'like', 'Awa%')->count());
    }

    public function test_un_doublon_interne_au_fichier_est_ecarte(): void
    {
        $this->ecrireCarnet([
            ['Awa Koffi',   '0707849883', '', 'Cocody', '', ''],
            ['Awa Koffi B', '0707849883', '', 'Cocody', '', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])
            ->expectsConfirmation('Créer ces 1 fiche(s) cliente ?', 'yes')
            ->assertSuccessful();

        $this->assertSame(1, User::where('phone', '0707849883')->count());
    }

    public function test_une_cliente_sans_email_recoit_une_adresse_inerte(): void
    {
        // `.local` n'est pas routable : aucun envoi ne peut partir vers un
        // inconnu si un email est déclenché par erreur sur cette fiche.
        $this->ecrireCarnet([
            ['Fatou Konaté', '0505112233', '', 'Yopougon', '', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])
            ->expectsConfirmation('Créer ces 1 fiche(s) cliente ?', 'yes')
            ->assertSuccessful();

        $this->assertStringEndsWith('@boutique.local', User::firstWhere('phone', '0505112233')->email);
    }

    public function test_une_ligne_sans_telephone_est_ecartee(): void
    {
        $this->ecrireCarnet([
            ['Sans Numéro', '', 'x@example.com', 'Marcory', '', ''],
            ['Avec Numéro', '0101223344', '', 'Marcory', '', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])
            ->expectsConfirmation('Créer ces 1 fiche(s) cliente ?', 'yes')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['name' => 'Sans Numéro']);
        $this->assertDatabaseHas('users', ['name' => 'Avec Numéro']);
    }

    public function test_le_mode_simulation_ne_cree_rien(): void
    {
        $this->ecrireCarnet([
            ['Awa Koffi', '0707849883', '', 'Cocody', '', ''],
        ]);

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['phone' => '0707849883']);
    }

    public function test_un_fichier_sans_colonne_telephone_est_refuse(): void
    {
        file_put_contents($this->fichier, "nom;email\nAwa;awa@example.com\n");

        $this->artisan('shop:import-customers', ['fichier' => $this->fichier])->assertFailed();
    }

    /** @param array<int, array<int, string>> $lignes */
    private function ecrireCarnet(array $lignes): void
    {
        $handle = fopen($this->fichier, 'w');
        fputcsv($handle, ['nom', 'telephone', 'email', 'commune', 'quartier', 'note'], ';', '"', '');

        foreach ($lignes as $ligne) {
            fputcsv($handle, $ligne, ';', '"', '');
        }

        fclose($handle);
    }
}
