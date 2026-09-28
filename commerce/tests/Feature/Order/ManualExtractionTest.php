<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\Order;
use Modules\Order\Support\ShipmentSheetScope;
use Tests\TestCase;

/**
 * Extraction manuelle, sur le calendrier de la tâche de 21 h.
 *
 *   Abidjan       dim → jeu   paiement non exigé (règlement à la livraison)
 *   Hors Abidjan  mar, jeu    payées uniquement
 *   Sous-région   mar, jeu    reste de l'Afrique, payées, frais arrêtés
 *   International dim → mer   reste du monde (DHL), payées, frais arrêtés
 *
 * Les deux mécanismes partagent la portée, la fenêtre et le marqueur : ce qui
 * est extrait à la main ne repart pas à 21 h, et inversement.
 */
class ManualExtractionTest extends TestCase
{
    use RefreshDatabase;

    // Semaine de référence : 2026-09-20 est un dimanche.
    private const DIMANCHE = '2026-09-20 10:00:00';
    private const MARDI    = '2026-09-22 10:00:00';
    private const VENDREDI = '2026-09-25 10:00:00';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function commande(string $country, string $city, bool $payee, array $extra = []): Order
    {
        $order = Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => User::factory()->create()->id,
            'status'           => 'pending',
            'subtotal'         => 5000,
            // Frais arrêtés : sans eux, l'international n'est jamais prêt.
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 6500,
            'currency'         => 'XOF',
            'paid_at'          => $payee ? now() : null,
            'shipping_address' => [
                'first_name' => 'Fatou', 'last_name' => 'Konaté',
                'city' => $city, 'commune' => $city === 'Abidjan' ? 'Cocody' : null,
                'country' => $country, 'phone' => '0102030405',
            ],
            'billing_address'  => ['city' => $city, 'country' => $country],
        ], array_diff_key($extra, ['created_at' => true])));

        // `created_at` est posé par Eloquent : il se force après coup.
        if (isset($extra['created_at'])) {
            $order->forceFill(['created_at' => $extra['created_at']])->saveQuietly();
        }

        return $order->refresh();
    }

    private function dues(string $zone): array
    {
        return ShipmentSheetScope::due($zone, ShipmentSheetScope::windowStart($zone), now())
            ->pluck('id')->all();
    }

    // ── Répartition ─────────────────────────────────────────────────────────

    public function test_chaque_commande_part_dans_la_bonne_zone(): void
    {
        $abidjan  = $this->commande('CI', 'Abidjan', false);
        $bouake   = $this->commande('CI', 'Bouaké', true);
        $dakar    = $this->commande('SN', 'Dakar', true);
        $casa     = $this->commande('MA', 'Casablanca', true);
        $paris    = $this->commande('FR', 'Paris', true);
        $montreal = $this->commande('CA', 'Montréal', true);

        $this->assertSame([$abidjan->id], $this->dues('abidjan'));
        $this->assertSame([$bouake->id], $this->dues('interior'));

        // Tout le continent africain hors Côte d'Ivoire.
        $this->assertEqualsCanonicalizing([$dakar->id, $casa->id], $this->dues('sous_region'));
        $this->assertEqualsCanonicalizing([$paris->id, $montreal->id], $this->dues('international'));
    }

    // ── Paiement ────────────────────────────────────────────────────────────

    public function test_abidjan_nexige_pas_le_paiement(): void
    {
        // Le livreur encaisse à la remise : exiger paid_at viderait la feuille.
        $impayee = $this->commande('CI', 'Abidjan', false);

        $this->assertSame([$impayee->id], $this->dues('abidjan'));
    }

    public function test_hors_abidjan_seules_les_payees_partent(): void
    {
        $this->commande('CI', 'Bouaké', false);
        $this->commande('SN', 'Dakar', false);
        $this->commande('FR', 'Paris', false);
        $payee = $this->commande('SN', 'Dakar', true);

        $this->assertSame([], $this->dues('interior'));
        $this->assertSame([$payee->id], $this->dues('sous_region'));
        $this->assertSame([], $this->dues('international'));
    }

    // ── Fenêtre sur le paiement ─────────────────────────────────────────────

    public function test_une_commande_ancienne_reglee_ce_jour_part_ce_soir(): void
    {
        // Créée il y a cinq jours, réglée ce matin. Datée par sa création, elle
        // serait restée hors de toute fenêtre ; datée par son paiement, elle
        // part avec l'extraction du jour.
        $commande = $this->commande('CI', 'Bouaké', true, ['created_at' => now()->subDays(5)]);

        $this->assertSame([$commande->id], $this->dues('interior'));
    }

    public function test_loubli_type_est_signale(): void
    {
        // Réglée avant la dernière extraction, mais jamais extraite : c'est la
        // commande que plus aucune fenêtre ne reprendrait.
        ShipmentSheetScope::rememberRun('interior', now()->subHour());

        $oubliee = $this->commande('CI', 'Bouaké', true, [
            'created_at' => now()->subDays(3),
            'paid_at'    => now()->subDays(2),
        ]);

        $this->assertSame([], $this->dues('interior'));
        $this->assertSame(
            [$oubliee->id],
            ShipmentSheetScope::overdue('interior', ShipmentSheetScope::windowStart('interior'))
                ->pluck('id')->all(),
        );
    }

    // ── Périmètre ───────────────────────────────────────────────────────────

    public function test_une_commande_deja_extraite_ne_repart_pas(): void
    {
        $this->commande('CI', 'Abidjan', false, ['exported_at' => now()]);

        $this->assertSame([], $this->dues('abidjan'));
    }

    public function test_une_commande_deja_expediee_ne_repart_pas(): void
    {
        $this->commande('CI', 'Abidjan', false, ['status' => 'delivered']);
        $this->commande('CI', 'Abidjan', false, ['status' => 'cancelled']);

        $this->assertSame([], $this->dues('abidjan'));
    }

    // ── Calendrier ──────────────────────────────────────────────────────────

    public function test_les_jours_de_passage(): void
    {
        $dimanche = Carbon::parse(self::DIMANCHE);
        $mardi    = Carbon::parse(self::MARDI);
        $vendredi = Carbon::parse(self::VENDREDI);

        $this->assertTrue(ShipmentSheetScope::isScheduled('abidjan', $dimanche));
        $this->assertFalse(ShipmentSheetScope::isScheduled('abidjan', $vendredi));

        $this->assertTrue(ShipmentSheetScope::isScheduled('interior', $mardi));
        $this->assertFalse(ShipmentSheetScope::isScheduled('interior', $vendredi));

        $this->assertTrue(ShipmentSheetScope::isScheduled('sous_region', $mardi));
        $this->assertFalse(ShipmentSheetScope::isScheduled('sous_region', $dimanche));

        $this->assertTrue(ShipmentSheetScope::isScheduled('international', $dimanche));
        $this->assertFalse(ShipmentSheetScope::isScheduled('international', $vendredi));
    }

    // ── Extraction ──────────────────────────────────────────────────────────

    public function test_lextraction_produit_le_fichier_et_marque_les_commandes(): void
    {
        Carbon::setTestNow(self::MARDI);

        $commande = $this->commande('SN', 'Dakar', true);

        Sanctum::actingAs($this->admin);

        $reponse = $this->post('/api/admin/orders/extractions/sous_region')->assertOk();

        // The plain-text delivery sheet, the one printed on the thermal printer.
        $this->assertStringStartsWith('text/plain', $reponse->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'extraction_sous_region_2026-09-22_10h00.txt',
            $reponse->headers->get('Content-Disposition'),
        );
        $this->assertSame('1', $reponse->headers->get('X-Extracted-Count'));
        $this->assertStringContainsString('FEUILLE DE LIVRAISON', $reponse->getContent());
        $this->assertStringContainsString($commande->number, $reponse->getContent());

        $this->assertNotNull($commande->fresh()->exported_at);
    }

    public function test_manual_extraction_opens_a_draft_round_like_the_evening_run(): void
    {
        Carbon::setTestNow(self::MARDI);

        // Cash on delivery: the round only takes unpaid orders that are COD.
        $order = $this->commande('CI', 'Abidjan', false, ['payment_method' => 'cod']);

        Sanctum::actingAs($this->admin);

        $response = $this->post('/api/admin/orders/extractions/abidjan')->assertOk();

        $round = \Modules\Order\Models\DeliveryRound::first();
        $this->assertNotNull($round);
        $this->assertSame(\Modules\Order\Models\DeliveryRound::STATUS_DRAFT, $round->status);

        // The code is returned to the screen and stamped on the sheet.
        $this->assertSame($round->code, $response->headers->get('X-Round-Code'));
        $this->assertStringContainsString($round->code, $response->getContent());

        // Nothing leaves in the evening: the morning confirmation commits it.
        $this->assertNull($order->fresh()->shipped_at);
    }

    public function test_les_fichiers_portent_le_nom_de_la_boutique(): void
    {
        Carbon::setTestNow(self::MARDI);

        $this->commande('CI', 'Bouaké', true);
        $this->commande('FR', 'Paris', true);

        Sanctum::actingAs($this->admin);

        $this->assertStringContainsString(
            'extraction_hors_abidjan_',
            $this->post('/api/admin/orders/extractions/interior')->assertOk()
                ->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString(
            'extraction_dhl_',
            $this->post('/api/admin/orders/extractions/international')->assertOk()
                ->headers->get('Content-Disposition'),
        );
    }

    public function test_lextraction_manuelle_fait_avancer_la_fenetre_de_21h(): void
    {
        Carbon::setTestNow(self::MARDI);

        $this->commande('CI', 'Bouaké', true);

        Sanctum::actingAs($this->admin);
        $this->post('/api/admin/orders/extractions/interior')->assertOk();

        // La tâche de 21 h repart de l'extraction manuelle : rien à renvoyer.
        $this->assertEquals(now(), ShipmentSheetScope::windowStart('interior'));
        $this->assertSame([], $this->dues('interior'));
    }

    public function test_hors_jour_de_passage_une_confirmation_est_demandee(): void
    {
        Carbon::setTestNow(self::VENDREDI);

        $commande = $this->commande('CI', 'Abidjan', false);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/extractions/abidjan')
            ->assertStatus(409)
            ->assertJsonPath('requires_confirmation', true);

        // Rien n'a été marqué tant que l'agente n'a pas confirmé.
        $this->assertNull($commande->fresh()->exported_at);

        $this->post('/api/admin/orders/extractions/abidjan', ['confirm_off_day' => 1])
            ->assertOk();

        $this->assertNotNull($commande->fresh()->exported_at);
    }

    public function test_rien_a_extraire_le_dit(): void
    {
        Carbon::setTestNow(self::MARDI);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/extractions/international')->assertStatus(422);
    }

    public function test_letat_des_zones(): void
    {
        Carbon::setTestNow(self::MARDI);

        $this->commande('CI', 'Abidjan', false);
        $this->commande('FR', 'Paris', true);

        Sanctum::actingAs($this->admin);

        $etat = collect($this->getJson('/api/admin/orders/extractions')->assertOk()->json('data'))
            ->keyBy('type');

        // Mardi : les quatre zones sont de passage.
        $this->assertCount(4, $etat);
        foreach (['abidjan', 'interior', 'sous_region', 'international'] as $zone) {
            $this->assertTrue($etat[$zone]['scheduled_today'], $zone);
        }

        $this->assertSame(1, $etat['abidjan']['pending_count']);
        $this->assertSame(0, $etat['sous_region']['pending_count']);
        $this->assertSame(1, $etat['international']['pending_count']);
        $this->assertSame('mardi, jeudi', $etat['interior']['days_label']);
        $this->assertSame('International (DHL)', $etat['international']['label']);
    }
}
