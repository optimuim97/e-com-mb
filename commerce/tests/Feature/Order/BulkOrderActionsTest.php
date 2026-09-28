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
 * Actions de masse sur les commandes : mise en préparation et marquage
 * « extraite ».
 *
 * Deux gestes quotidiens que l'administration imposait de répéter commande par
 * commande. Ils partagent la même exigence : rendre compte de ce qui n'a pas
 * été fait, sinon un « 27 traitées » sur 30 oblige à tout reprendre à la main.
 */
class BulkOrderActionsTest extends TestCase
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

    private function commande(string $status): Order
    {
        return Order::factory()->create(['status' => $status]);
    }

    // ── Mise en préparation ────────────────────────────────────────────────

    public function test_les_commandes_en_attente_passent_en_preparation(): void
    {
        $une   = $this->commande('pending');
        $deux  = $this->commande('confirmed');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-process', [
            'order_ids' => [$une->id, $deux->id],
        ])->assertOk()->assertJsonCount(2, 'processed');

        $this->assertSame('processing', $une->fresh()->status);
        $this->assertSame('processing', $deux->fresh()->status);
    }

    public function test_une_commande_expediee_nest_pas_ramenee_en_arriere(): void
    {
        $expediee = $this->commande('shipped');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-process', ['order_ids' => [$expediee->id]])
            ->assertOk()
            // Ni traitée ni écartée : il n'y a simplement rien à faire, et la
            // compter comme refusée ferait croire à un problème.
            ->assertJsonCount(0, 'processed')
            ->assertJsonCount(0, 'rejected');

        $this->assertSame('shipped', $expediee->fresh()->status);
    }

    public function test_une_commande_annulee_est_ecartee_avec_son_motif(): void
    {
        $annulee = $this->commande('cancelled');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-process', ['order_ids' => [$annulee->id]])
            ->assertOk()
            ->assertJsonCount(0, 'processed')
            ->assertJsonCount(1, 'rejected')
            ->assertJsonPath('rejected.0.number', $annulee->number)
            ->assertJsonPath('rejected.0.forceable', false);

        $this->assertSame('cancelled', $annulee->fresh()->status);
    }

    // ── Marquage « extraite » ──────────────────────────────────────────────

    public function test_le_marquage_horodate_les_commandes(): void
    {
        $une  = $this->commande('pending');
        $deux = $this->commande('processing');

        $this->assertNull($une->exported_at);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-mark-exported', [
            'order_ids' => [$une->id, $deux->id],
        ])->assertOk();

        $this->assertNotNull($une->fresh()->exported_at);
        $this->assertNotNull($deux->fresh()->exported_at);
    }

    public function test_le_marquage_ne_touche_pas_au_statut(): void
    {
        $commande = $this->commande('pending');

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/orders/bulk-mark-exported', ['order_ids' => [$commande->id]])->assertOk();

        // L'extraction dit qu'une feuille est sortie, pas que la commande a
        // avancé : les deux informations doivent rester indépendantes.
        $this->assertSame('pending', $commande->fresh()->status);
    }

    public function test_la_date_dextraction_est_exposee_par_lapi(): void
    {
        $commande = $this->commande('pending');

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/orders/bulk-mark-exported', ['order_ids' => [$commande->id]])->assertOk();

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.exported_at', fn ($value) => $value !== null);
    }

    // ── Marquage « payée » ─────────────────────────────────────────────────

    private function commandeInterieur(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'           => 'pending',
            'destination'      => 'interior',
            'shipping_cost'    => 3000,
            'total'            => 13000,
            'shipping_address' => [
                'first_name'    => 'Awa',
                'last_name'     => 'Koné',
                'address_line1' => 'Quartier Commerce',
                'city'          => 'Bouaké',
                'country'       => 'CI',
            ],
        ], $overrides));
    }

    public function test_le_marquage_payee_a_le_meme_effet_qua_lunite(): void
    {
        $abidjan   = $this->commande('pending');
        $interieur = $this->commandeInterieur();

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-mark-paid', [
            'order_ids' => [$abidjan->id, $interieur->id],
        ])->assertOk()->assertJsonCount(2, 'paid')->assertJsonCount(0, 'rejected');

        $this->assertNotNull($abidjan->fresh()->paid_at);
        // Abidjan encaisse à la livraison : le règlement ne confirme rien.
        $this->assertSame('pending', $abidjan->fresh()->status);
        // Une expédition payée est confirmée, comme au marquage unitaire.
        $this->assertSame('confirmed', $interieur->fresh()->status);
    }

    public function test_une_commande_deja_payee_garde_sa_date_dencaissement(): void
    {
        $payee = $this->commande('processing');
        $payee->update(['paid_at' => now()->subDays(3)]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-mark-paid', ['order_ids' => [$payee->id]])
            ->assertOk()->assertJsonCount(0, 'paid')->assertJsonCount(0, 'rejected');

        $this->assertTrue($payee->fresh()->paid_at->lt(now()->subDays(2)));
    }

    public function test_des_frais_a_fixer_bloquent_le_marquage_payee_sauf_en_force(): void
    {
        $sansFrais = $this->commandeInterieur(['shipping_cost' => 0, 'total' => 10000]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-mark-paid', ['order_ids' => [$sansFrais->id]])
            ->assertOk()
            ->assertJsonCount(0, 'paid')
            ->assertJsonPath('rejected.0.forceable', true);
        $this->assertNull($sansFrais->fresh()->paid_at);

        $this->postJson('/api/admin/orders/bulk-mark-paid', ['order_ids' => [$sansFrais->id], 'force' => true])
            ->assertOk()->assertJsonCount(1, 'paid');
        $this->assertNotNull($sansFrais->fresh()->paid_at);
    }

    public function test_une_commande_annulee_nest_pas_marquee_payee(): void
    {
        $annulee = $this->commande('cancelled');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-mark-paid', ['order_ids' => [$annulee->id], 'force' => true])
            ->assertOk()
            ->assertJsonCount(0, 'paid')
            ->assertJsonPath('rejected.0.forceable', false);
        $this->assertNull($annulee->fresh()->paid_at);
    }

    // ── Marquage « livrée » ────────────────────────────────────────────────

    public function test_les_expediees_passent_livrees_et_les_impayees_sont_signalees(): void
    {
        $payee   = $this->commande('shipped');
        $payee->update(['paid_at' => now()]);
        $impayee = $this->commande('shipped');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-deliver', [
            'order_ids' => [$payee->id, $impayee->id],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'delivered')
            ->assertJsonCount(1, 'to_collect')
            ->assertJsonPath('to_collect.0.id', $impayee->id);

        $this->assertSame('delivered', $payee->fresh()->status);
        $this->assertNotNull($impayee->fresh()->delivered_at);
        // Livrer n'est pas encaisser.
        $this->assertNull($impayee->fresh()->paid_at);
    }

    public function test_livrer_une_commande_jamais_expediee_demande_de_forcer(): void
    {
        $retrait = $this->commande('processing');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-deliver', ['order_ids' => [$retrait->id]])
            ->assertOk()
            ->assertJsonCount(0, 'delivered')
            ->assertJsonPath('rejected.0.forceable', true);
        $this->assertSame('processing', $retrait->fresh()->status);

        $this->postJson('/api/admin/orders/bulk-deliver', ['order_ids' => [$retrait->id], 'force' => true])
            ->assertOk()->assertJsonCount(1, 'delivered');
        $this->assertSame('delivered', $retrait->fresh()->status);
    }

    public function test_une_commande_en_tournee_se_pointe_depuis_la_tournee(): void
    {
        $enRoute = $this->commande('processing');
        $enRoute->update(['paid_at' => now(), 'shipping_cost' => 1500, 'total' => 11500]);

        Sanctum::actingAs($this->admin);

        $code = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$enRoute->id]])
            ->assertCreated()->json('data.code');
        $this->assertSame('shipped', $enRoute->fresh()->status);

        // Même en force : la ligne resterait « à pointer » dans la tournée.
        $this->postJson('/api/admin/orders/bulk-deliver', ['order_ids' => [$enRoute->id], 'force' => true])
            ->assertOk()
            ->assertJsonCount(0, 'delivered')
            ->assertJsonPath('rejected.0.forceable', false)
            ->assertJsonPath('rejected.0.reasons.0', fn ($motif) => str_contains($motif, $code));

        $this->assertSame('shipped', $enRoute->fresh()->status);
    }

    // ── Annulation ─────────────────────────────────────────────────────────

    public function test_lannulation_en_masse_passe_par_le_service(): void
    {
        $doublon = $this->commande('pending');
        $autre   = $this->commande('processing');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-cancel', ['order_ids' => [$doublon->id, $autre->id]])
            ->assertOk()->assertJsonCount(2, 'cancelled');

        $this->assertSame('cancelled', $doublon->fresh()->status);
        $this->assertSame('cancelled', $autre->fresh()->status);
    }

    public function test_un_colis_parti_ne_sannule_pas_en_masse(): void
    {
        $expediee = $this->commande('shipped');
        $livree   = $this->commande('delivered');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-cancel', [
            'order_ids' => [$expediee->id, $livree->id],
            'force'     => true,
        ])
            ->assertOk()
            ->assertJsonCount(0, 'cancelled')
            ->assertJsonCount(2, 'rejected')
            ->assertJsonPath('rejected.0.forceable', false)
            ->assertJsonPath('rejected.1.forceable', false);

        $this->assertSame('shipped', $expediee->fresh()->status);
        $this->assertSame('delivered', $livree->fresh()->status);
    }

    public function test_annuler_une_commande_payee_demande_de_forcer(): void
    {
        $payee = $this->commande('pending');
        $payee->update(['paid_at' => now()]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-cancel', ['order_ids' => [$payee->id]])
            ->assertOk()
            ->assertJsonCount(0, 'cancelled')
            ->assertJsonPath('rejected.0.forceable', true);
        $this->assertSame('pending', $payee->fresh()->status);

        $this->postJson('/api/admin/orders/bulk-cancel', ['order_ids' => [$payee->id], 'force' => true])
            ->assertOk()->assertJsonCount(1, 'cancelled');
        $this->assertSame('cancelled', $payee->fresh()->status);
    }

    public function test_une_commande_dun_brouillon_de_tournee_nest_pas_annulee(): void
    {
        $prevue = $this->commande('processing');
        $prevue->update(['paid_at' => now(), 'shipping_cost' => 1500, 'total' => 11500]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$prevue->id], 'draft' => true])
            ->assertCreated();

        $this->postJson('/api/admin/orders/bulk-cancel', ['order_ids' => [$prevue->id], 'force' => true])
            ->assertOk()
            ->assertJsonCount(0, 'cancelled')
            ->assertJsonPath('rejected.0.forceable', false);

        $this->assertSame('processing', $prevue->fresh()->status);
    }

    // ── Aperçu d'extraction d'une sélection ────────────────────────────────

    public function test_lapercu_dextraction_se_limite_a_la_selection(): void
    {
        $cochee    = $this->commande('processing');
        $nonCochee = $this->commande('processing');

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/export-preview', ['order_ids' => [$cochee->id]])
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $cochee->id);
    }

    // ── Habilitations ──────────────────────────────────────────────────────

    public function test_une_cliente_ne_peut_pas_lancer_une_action_de_masse(): void
    {
        $commande = $this->commande('pending');

        $cliente = User::factory()->create();
        $cliente->syncRoles(['customer']);

        Sanctum::actingAs($cliente);

        foreach (['bulk-process', 'bulk-mark-exported', 'bulk-mark-paid', 'bulk-deliver', 'bulk-cancel'] as $action) {
            $this->postJson("/api/admin/orders/{$action}", ['order_ids' => [$commande->id]])
                ->assertForbidden();
        }
    }

    public function test_une_liste_vide_est_refusee(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/orders/bulk-process', ['order_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_ids');
    }

    // ── Pagination : la forme lue par l'écran d'administration ─────────────

    public function test_la_liste_expose_ses_compteurs_de_pagination(): void
    {
        // Le contrôleur borne `per_page` à 5 au minimum : il faut donc plus de
        // cinq commandes pour qu'une seconde page existe.
        Order::factory()->count(12)->create();

        Sanctum::actingAs($this->admin);

        // `OrderResource::collection` range la pagination sous `meta` : le front
        // la lisait à la racine, où elle n'a jamais été, et masquait donc la
        // pagination faute de total.
        $this->getJson('/api/admin/orders?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(5, 'data');
    }
}
