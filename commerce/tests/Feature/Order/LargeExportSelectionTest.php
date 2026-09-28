<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Extraction d'une sélection volumineuse.
 *
 * Les feuilles de livraison partaient en GET avec la sélection dans l'URL.
 * Sept cents commandes font une ligne de requête d'environ 14 Ko, là où nginx
 * en accepte 8 : l'export échouait en 414 précisément sur les gros lots, ceux
 * qui justifient l'outil. La sélection voyage désormais dans le corps.
 */
class LargeExportSelectionTest extends TestCase
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
        Sanctum::actingAs($this->admin);
    }

    private function commandes(int $nombre): array
    {
        return Order::factory()->count($nombre)->create([
            'status'           => 'processing',
            'paid_at'          => now(),
            // Commande invitée : le nom ne peut alors venir que de l'adresse,
            // ce qui est le cas courant en boutique.
            'user_id'          => null,
            'shipping_address' => [
                'first_name'    => 'Awa',
                'last_name'     => 'Koné',
                'address_line1' => 'Rue du marché',
                'city'          => 'Abidjan',
                'commune'       => 'Cocody',
                'country'       => 'CI',
                'phone'         => '0102030405',
            ],
        ])->pluck('id')->all();
    }

    public function test_une_grosse_selection_passe_en_post(): void
    {
        $ids = $this->commandes(300);

        // La même sélection en URL ferait plusieurs kilo-octets ; dans le corps,
        // la taille n'entre plus en jeu.
        $reponse = $this->post('/api/admin/orders/export-by-zone-txt', [
            'order_ids' => $ids,
            'label'     => 'Commandes du jour',
        ]);

        $reponse->assertOk();
        $this->assertStringContainsString('text/plain', $reponse->headers->get('content-type'));
    }

    public function test_le_get_reste_accepte_pour_les_petites_selections(): void
    {
        $ids = $this->commandes(3);

        // Les liens déjà partagés et le débogage à la main doivent continuer
        // de fonctionner.
        $this->get('/api/admin/orders/export-by-zone-txt?' . http_build_query(['order_ids' => $ids]))
            ->assertOk();
    }

    public function test_les_autres_exports_acceptent_aussi_le_post(): void
    {
        $ids = $this->commandes(3);

        $this->post('/api/admin/orders/export', ['order_ids' => $ids, 'format' => 'csv'])->assertOk();
        $this->post('/api/admin/orders/export-by-zone', ['order_ids' => $ids])->assertOk();
        $this->post('/api/admin/orders/export-shipments', ['tab' => 'interior', 'format' => 'csv'])->assertOk();
    }

    // ── Nom et numéro sur la feuille ───────────────────────────────────────

    public function test_la_feuille_texte_porte_le_nom_de_la_cliente(): void
    {
        $ids = $this->commandes(1);

        $feuille = $this->post('/api/admin/orders/export-by-zone-txt', ['order_ids' => $ids])
            ->assertOk()
            ->getContent();

        // C'est le nom que le livreur annonce en arrivant, et qu'il demande à
        // la porte quand plusieurs foyers partagent une cour.
        $this->assertStringContainsString('Awa Koné', $feuille);
    }

    public function test_lexport_pdf_par_zone_aboutit(): void
    {
        $ids = $this->commandes(2);

        // Le gabarit lit `$order['name']`, que le constructeur de données ne
        // fournissait plus : l'export PDF échouait en « Undefined array key ».
        $this->post('/api/admin/orders/export-by-zone', ['order_ids' => $ids])->assertOk();
    }

    public function test_la_feuille_des_expeditions_garde_les_impayees(): void
    {
        $impayee = Order::factory()->create([
            'status'           => 'pending',
            'paid_at'          => null,
            'destination'      => 'interior',
            'user_id'          => null,
            'shipping_address' => [
                'first_name' => 'Mariam', 'last_name' => 'Traoré',
                'city' => 'Bouaké', 'commune' => 'Bouaké',
                'country' => 'CI', 'phone' => '0102030405',
            ],
        ]);

        $feuille = $this->post('/api/admin/orders/export-shipments', [
            'tab' => 'interior', 'format' => 'txt',
        ])->assertOk()->getContent();

        // La règle « hors Abidjan, rien d'impayé » viderait cette feuille de
        // tout ce que l'écran sert précisément à traiter.
        $this->assertStringContainsString($impayee->number, $feuille);
        $this->assertStringContainsString('Mariam Traoré', $feuille);
    }

    // ── Numéro de commande sur la feuille ──────────────────────────────────

    public function test_la_feuille_texte_porte_le_numero_de_commande(): void
    {
        $ids = $this->commandes(2);
        $numeros = Order::whereIn('id', $ids)->pluck('number');

        $feuille = $this->post('/api/admin/orders/export-by-zone-txt', ['order_ids' => $ids])
            ->assertOk()
            ->getContent();

        // Sans lui, un litige au retour obligeait à retrouver la commande par
        // l'adresse et le téléphone.
        foreach ($numeros as $numero) {
            $this->assertStringContainsString($numero, $feuille);
        }
    }

    public function test_le_numero_ne_deborde_pas_de_la_largeur_dimpression(): void
    {
        $commande = Order::factory()->create([
            'status'           => 'processing',
            'paid_at'          => now(),
            'user_id'          => null,
            'shipping_address' => [
                'first_name'    => 'Awa',
                'last_name'     => 'Koné',
                'address_line1' => 'Rue du marché',
                'city'          => 'Abidjan',
                // Localité volontairement longue : c'est elle qui doit céder,
                // pas le numéro. La thermique 80 mm imprime 48 colonnes.
                'commune'       => 'Cocody Riviera Palmeraie Extension Nord',
                'country'       => 'CI',
                'phone'         => '0102030405',
            ],
        ]);

        $feuille = $this->post('/api/admin/orders/export-by-zone-txt', ['order_ids' => [$commande->id]])
            ->assertOk()
            ->getContent();

        $ligne = collect(explode("\r\n", $feuille))
            ->first(fn (string $l) => str_contains($l, $commande->number));

        $this->assertNotNull($ligne, 'Le numéro de commande est absent de la feuille.');
        $this->assertLessThanOrEqual(48, mb_strlen(ltrim($ligne, "\u{FEFF}")));
        $this->assertStringEndsWith($commande->number, $ligne);
    }
}
