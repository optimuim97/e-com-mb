<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Exports\OrdersExport;
use Modules\Order\Exports\ShipmentsExport;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Extraction de l'écran des expéditions.
 *
 * Elle ne pouvait pas réutiliser l'export général : celui-ci écarte les
 * commandes non payées hors Abidjan — la règle qui décide de ce qui peut
 * partir, pas de ce que l'agent doit voir. Appliquée ici, elle vide les trois
 * onglets, dont la raison d'être est justement de traiter ces commandes.
 */
class ShipmentsExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
    }

    private function commande(array $attrs = [], array $adresse = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'           => 'pending',
            'paid_at'          => null,
            'destination'      => 'interior',
            // Commande invitée par défaut : c'est le cas courant en boutique,
            // et celui où le nom ne peut venir que de l'adresse.
            'user_id'          => null,
            'shipping_address' => array_merge([
                'first_name' => 'Awa',
                'last_name'  => 'Koné',
                'city'       => 'Bouaké',
                'commune'    => 'Bouaké',
                'country'    => 'CI',
                'phone'      => '0102030405',
            ], $adresse),
        ], $attrs));
    }

    // ── Population ─────────────────────────────────────────────────────────

    public function test_lexport_general_ecarte_ce_que_lecran_affiche(): void
    {
        $commande = $this->commande();

        // Garde-fou : si cette règle changeait, l'extraction dédiée perdrait sa
        // raison d'être et ce test doit le signaler.
        $this->assertCount(0, OrdersExport::resolve([]));
        $this->assertCount(1, ShipmentsExport::resolve('interior'));
        $this->assertSame($commande->id, ShipmentsExport::resolve('interior')->first()->id);
    }

    public function test_chaque_onglet_rend_sa_propre_population(): void
    {
        $interieur = $this->commande();
        $intl      = $this->commande(['destination' => 'international'], ['country' => 'FR']);
        $aTarifer  = $this->commande(['metadata' => ['shipping_unknown' => true]]);

        $this->assertSame([$interieur->id, $aTarifer->id], ShipmentsExport::resolve('interior')->pluck('id')->sort()->values()->all());
        $this->assertSame([$intl->id], ShipmentsExport::resolve('international')->pluck('id')->all());
        $this->assertSame([$aTarifer->id], ShipmentsExport::resolve('a_tarifer')->pluck('id')->all());
    }

    public function test_une_selection_explicite_lemporte_sur_longlet(): void
    {
        $this->commande();
        $choisie = $this->commande(['destination' => 'international'], ['country' => 'FR']);

        // L'agent a déjà arbitré à l'écran : l'onglet n'a plus à retrancher.
        $retenues = ShipmentsExport::resolve('interior', [$choisie->id]);

        $this->assertSame([$choisie->id], $retenues->pluck('id')->all());
    }

    // ── Colonnes ───────────────────────────────────────────────────────────

    public function test_le_format_court_ne_rend_que_client_et_numero(): void
    {
        $commande = $this->commande();
        $export   = new ShipmentsExport(columns: ShipmentsExport::COLUMNS_MINIMAL);

        $this->assertSame(['Client', 'N° Commande'], $export->headings());
        $this->assertSame(['Awa Koné', $commande->number], $export->map($commande));
    }

    public function test_le_nom_du_compte_prime_sur_ladresse(): void
    {
        $cliente  = User::factory()->create(['name' => 'Mariam Traoré']);
        $commande = $this->commande(['user_id' => $cliente->id]);

        $export = new ShipmentsExport(columns: ShipmentsExport::COLUMNS_MINIMAL);

        $this->assertSame('Mariam Traoré', $export->map($commande->fresh('user'))[0]);
    }

    public function test_le_format_complet_porte_le_detail_dexpedition(): void
    {
        $commande = $this->commande();
        $export   = new ShipmentsExport();

        $entetes = $export->headings();

        $this->assertSame('Client', $entetes[0]);
        $this->assertSame('N° Commande', $entetes[1]);
        $this->assertContains('Téléphone', $entetes);
        $this->assertContains('Commune', $entetes);
        $this->assertSame(count($entetes), count($export->map($commande)));
    }

    public function test_des_frais_non_arretes_restent_vides(): void
    {
        $commande = $this->commande([
            'metadata'      => ['shipping_unknown' => true],
            'shipping_cost' => 0,
        ]);

        $export = new ShipmentsExport();
        $ligne  = $export->map($commande);
        $colonne = array_search('Frais de livraison', $export->headings(), true);

        // Un zéro se lirait comme une livraison gratuite ; la case vide dit
        // « à remplir ».
        $this->assertSame('', $ligne[$colonne]);
    }

    // ── Endpoint ───────────────────────────────────────────────────────────

    public function test_lextraction_est_telechargeable(): void
    {
        $this->commande();

        Sanctum::actingAs($this->admin);

        $reponse = $this->get('/api/admin/orders/export-shipments?tab=interior&columns=minimal&format=csv');

        $reponse->assertOk();
        $this->assertStringContainsString('expeditions-', $reponse->headers->get('content-disposition'));
    }

    public function test_un_onglet_inconnu_est_refuse(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/orders/export-shipments?tab=nimporte')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tab');
    }

    public function test_lextraction_exige_lhabilitation(): void
    {
        $cliente = User::factory()->create();
        $cliente->syncRoles(['customer']);

        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/orders/export-shipments?tab=interior')->assertForbidden();
    }
}
