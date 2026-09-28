<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * La tournée préparée la veille.
 *
 * L'extraction du soir fige une sélection et lui donne un code ; le lendemain
 * matin, la boutique désigne un livreur et confirme le départ. Ce qui est
 * vérifié ici tient à la seule question que ce découpage pose : entre 21 h et
 * le départ, l'état des commandes a pu changer. Une commande réglée dans la
 * nuit doit pouvoir partir, une commande annulée ne doit pas, et rien ne doit
 * être expédié tant que personne n'a confirmé.
 */
class DeliveryRoundDraftTest extends TestCase
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

    private function commandePrete(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'        => 'processing',
            'paid_at'       => now(),
            'shipping_cost' => 1500,
            'total'         => 11500,
        ], $overrides));
    }

    /** @param array<int, int> $ids */
    private function brouillon(array $ids): string
    {
        return $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => $ids,
            'draft'     => true,
        ])->assertCreated()->json('data.code');
    }

    private function livreur(string $nom = 'Koffi Yao'): User
    {
        $user = User::factory()->create(['name' => $nom]);
        $user->syncRoles(['livreur']);

        return $user;
    }

    // ── Préparation ─────────────────────────────────────────────────────────

    public function test_un_brouillon_nexpedie_rien(): void
    {
        $order = $this->commandePrete();

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$order->id],
            'draft'     => true,
        ])->assertCreated();

        $this->assertSame(DeliveryRound::STATUS_DRAFT, $reponse->json('data.status'));
        $this->assertNull($reponse->json('data.dispatched_at'));

        // Le colis est encore en boutique : la commande ne peut pas être
        // annoncée expédiée à la cliente.
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertNull($order->fresh()->shipped_at);
    }

    public function test_un_brouillon_reserve_ses_commandes(): void
    {
        $order = $this->commandePrete();
        $code  = $this->brouillon([$order->id]);

        // Composée à la main le lendemain matin, une seconde tournée ne doit
        // pas embarquer ce que le brouillon du soir a déjà retenu.
        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->assertStatus(422)
            ->assertJsonPath('rejected.0.reasons.0', "Déjà dans la tournée {$code}.");
    }

    public function test_le_brouillon_sort_en_tete_de_liste(): void
    {
        $this->brouillon([$this->commandePrete()->id]);

        $liste = $this->getJson('/api/admin/delivery-rounds?status=draft')
            ->assertOk()
            ->json('data');

        // Sans date de départ, un tri sur `dispatched_at` seul reléguerait le
        // brouillon en fin de liste — soit exactement la tournée qu'on vient
        // chercher le matin.
        $this->assertCount(1, $liste);
        $this->assertSame(DeliveryRound::STATUS_DRAFT, $liste[0]['status']);
    }

    // ── Confirmation du départ ──────────────────────────────────────────────

    public function test_confirmer_le_depart_affecte_le_livreur_et_expedie(): void
    {
        $order   = $this->commandePrete();
        $code    = $this->brouillon([$order->id]);
        $livreur = $this->livreur();

        $reponse = $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch", [
            'courier_id' => $livreur->id,
        ])->assertOk();

        $this->assertSame(DeliveryRound::STATUS_DISPATCHED, $reponse->json('data.status'));
        $this->assertSame($livreur->id, $reponse->json('data.courier.id'));
        $this->assertNotNull($reponse->json('data.dispatched_at'));

        $this->assertSame('shipped', $order->fresh()->status);
    }

    /**
     * Le point de tout le découpage : c'est l'état du matin qui fait foi, pas
     * celui de 21 h.
     */
    public function test_une_commande_reglee_dans_la_nuit_part_le_matin(): void
    {
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        // Elle n'entre pas dans le brouillon du soir…
        $bonne = $this->commandePrete();
        $code  = $this->brouillon([$bonne->id, $impayee->id]);

        $this->assertSame(1, $this->getJson("/api/admin/delivery-rounds/{$code}")
            ->json('data.orders_count'));

        // …mais rien n'empêche de la régler puis de l'ajouter à une tournée.
        $impayee->update(['paid_at' => now()]);

        $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$impayee->id],
        ])->assertCreated();

        $this->assertSame('shipped', $impayee->fresh()->status);
    }

    public function test_une_commande_annulee_entre_temps_ne_part_pas(): void
    {
        $bonne   = $this->commandePrete();
        $annulee = $this->commandePrete();

        $code = $this->brouillon([$bonne->id, $annulee->id]);

        // La cliente se ravise dans la nuit.
        $annulee->update(['status' => 'cancelled']);

        $reponse = $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch")
            ->assertOk();

        $this->assertSame(1, $reponse->json('data.orders_count'));
        $this->assertSame($annulee->number, $reponse->json('rejected.0.number'));

        // La ligne quitte la tournée : l'y laisser ferait attendre au retour un
        // pointage qui ne viendrait jamais.
        $this->assertSame($bonne->id, $reponse->json('data.lines.0.order.id'));
        $this->assertSame('cancelled', $annulee->fresh()->status);
    }

    public function test_un_brouillon_entierement_bloque_ne_part_pas(): void
    {
        $order = $this->commandePrete();
        $code  = $this->brouillon([$order->id]);

        $order->update(['status' => 'cancelled']);

        $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch")
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_shippable_orders');

        $this->assertSame(
            DeliveryRound::STATUS_DRAFT,
            DeliveryRound::where('code', $code)->value('status'),
        );
    }

    public function test_une_tournee_deja_partie_ne_repart_pas(): void
    {
        $code = $this->brouillon([$this->commandePrete()->id]);

        $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch")->assertOk();

        $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch")
            ->assertStatus(422)
            ->assertJsonPath('code', 'round_already_dispatched');
    }

    public function test_on_ne_designe_pas_un_livreur_qui_nen_est_pas_un(): void
    {
        $code     = $this->brouillon([$this->commandePrete()->id]);
        $vendeuse = User::factory()->create();
        $vendeuse->syncRoles(['staff']);

        // Affecter la gérante ou une vendeuse ferait apparaître la tournée dans
        // un espace livreur qui n'est pas le sien.
        $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch", [
            'courier_id' => $vendeuse->id,
        ])->assertStatus(422)->assertJsonPath('code', 'not_a_courier');
    }

    public function test_un_brouillon_ne_se_pointe_pas(): void
    {
        $code   = $this->brouillon([$this->commandePrete()->id]);
        $detail = $this->getJson("/api/admin/delivery-rounds/{$code}")->json('data');

        $this->patchJson(
            "/api/admin/delivery-rounds/{$code}/lines/{$detail['lines'][0]['id']}",
            ['status' => 'delivered'],
        )->assertStatus(422)->assertJsonPath('code', 'round_not_dispatched');
    }

    // ── Abandon ─────────────────────────────────────────────────────────────

    public function test_abandonner_un_brouillon_libere_ses_commandes(): void
    {
        $order = $this->commandePrete();
        $code  = $this->brouillon([$order->id]);

        $this->deleteJson("/api/admin/delivery-rounds/{$code}")->assertOk();

        $this->assertDatabaseCount('delivery_rounds', 0);
        $this->assertDatabaseCount('delivery_round_orders', 0);

        // Sans cette libération, une tournée préparée puis oubliée garderait ses
        // commandes prisonnières d'une sortie qui n'aura jamais lieu.
        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->assertCreated();
    }

    public function test_une_tournee_partie_ne_sefface_pas(): void
    {
        $code = $this->brouillon([$this->commandePrete()->id]);
        $this->postJson("/api/admin/delivery-rounds/{$code}/dispatch")->assertOk();

        $this->deleteJson("/api/admin/delivery-rounds/{$code}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'round_dispatched');
    }

    // ── Création d'un livreur ───────────────────────────────────────────────

    public function test_creer_un_livreur_depuis_lecran_des_tournees(): void
    {
        $reponse = $this->postJson('/api/admin/delivery-rounds/couriers', [
            'name'     => 'Koffi Yao',
            'phone'    => '0102030405',
            'password' => 'motdepasse8',
        ])->assertCreated();

        $id = $reponse->json('data.id');

        // L'identifiant est rendu : c'est ce que la boutique dicte au livreur.
        $this->assertNotEmpty($reponse->json('data.username'));
        $this->assertTrue(User::find($id)->hasRole('livreur'));

        // Et il apparaît aussitôt dans le select de la confirmation.
        $this->assertContains(
            $id,
            collect($this->getJson('/api/admin/delivery-rounds/couriers')->json('data'))
                ->pluck('id')->all(),
        );
    }

    /**
     * Le rôle n'est pas un paramètre : c'est ce qui rend l'ouverture acceptable.
     * `rounds.edit` crée des livreurs, pas des comptes d'administration.
     */
    public function test_le_role_du_compte_cree_ne_se_choisit_pas(): void
    {
        $reponse = $this->postJson('/api/admin/delivery-rounds/couriers', [
            'name'     => 'Faux Admin',
            'password' => 'motdepasse8',
            'role'     => 'admin',
        ])->assertCreated();

        $user = User::find($reponse->json('data.id'));

        $this->assertTrue($user->hasRole('livreur'));
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_creer_un_livreur_exige_lhabilitation_des_tournees(): void
    {
        $intrus = User::factory()->create();
        $intrus->syncRoles(['customer']);
        Sanctum::actingAs($intrus);

        $this->postJson('/api/admin/delivery-rounds/couriers', [
            'name'     => 'Koffi Yao',
            'password' => 'motdepasse8',
        ])->assertForbidden();
    }
}
