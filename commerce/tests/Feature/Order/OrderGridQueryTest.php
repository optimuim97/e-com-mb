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
 * Le tableau des commandes, filtré et trié par colonne.
 *
 * Le tableau ne détient jamais toutes les commandes : il les demande page par
 * page, et chaque filtre de colonne doit donc devenir une clause côté serveur.
 * Ce qui est vérifié : chaque opérateur fait ce que son libellé annonce, les
 * dates se comparent au jour près, et rien de ce qui vient du navigateur
 * n'atteint la requête sans passer par la liste des colonnes autorisées.
 */
class OrderGridQueryTest extends TestCase
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

    private function commande(array $attrs = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'  => 'pending',
            'user_id' => null,
            'total'   => 10000,
            'shipping_address' => [
                'first_name' => 'Awa', 'last_name' => 'Koné',
                'phone' => '0102030405', 'commune' => 'Cocody', 'city' => 'Abidjan',
            ],
        ], $attrs));
    }

    /** @return array<int, string> numéros renvoyés */
    private function numeros(array $params): array
    {
        return collect($this->getJson('/api/admin/orders?' . http_build_query($params))
            ->assertOk()->json('data'))->pluck('number')->all();
    }

    private function filtre(array $model): array
    {
        return ['filter' => json_encode($model), 'per_page' => 100];
    }

    // ── Dates ──────────────────────────────────────────────────────────────

    public function test_hier_et_aujourdhui_incluent_les_deux_journees_entieres(): void
    {
        $aujourdhui = $this->commande(['created_at' => now()->setTime(8, 0)]);
        $hierSoir   = $this->commande(['created_at' => now()->subDay()->setTime(23, 50)]);
        $avantHier  = $this->commande(['created_at' => now()->subDays(2)->setTime(12, 0)]);

        $numeros = $this->numeros($this->filtre(['created_at' => [
            'filterType' => 'date', 'type' => 'inRange',
            'dateFrom'   => now()->subDay()->format('Y-m-d') . ' 00:00:00',
            'dateTo'     => now()->format('Y-m-d') . ' 00:00:00',
        ]]));

        // Le tableau envoie des minuits : comparer à l'instant exclurait la
        // commande d'hier 23 h 50 et toute celle d'aujourd'hui.
        $this->assertEqualsCanonicalizing([$aujourdhui->number, $hierSoir->number], $numeros);
        $this->assertNotContains($avantHier->number, $numeros);
    }

    public function test_une_date_egale_designe_la_journee(): void
    {
        $matin = $this->commande(['created_at' => now()->setTime(7, 0)]);
        $soir  = $this->commande(['created_at' => now()->setTime(21, 30)]);
        $this->commande(['created_at' => now()->subDay()]);

        $numeros = $this->numeros($this->filtre(['created_at' => [
            'filterType' => 'date', 'type' => 'equals', 'dateFrom' => now()->format('Y-m-d') . ' 00:00:00',
        ]]));

        $this->assertEqualsCanonicalizing([$matin->number, $soir->number], $numeros);
    }

    // ── Texte ──────────────────────────────────────────────────────────────

    public function test_le_client_se_cherche_dans_le_compte_et_dans_ladresse(): void
    {
        $parAdresse = $this->commande();
        $compte     = User::factory()->create(['name' => 'Mariam Traoré', 'email' => 'mariam@exemple.ci']);
        $parCompte  = $this->commande(['user_id' => $compte->id, 'shipping_address' => ['first_name' => 'X', 'last_name' => 'Y']]);

        $this->assertSame([$parAdresse->number], $this->numeros($this->filtre([
            'customer' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'kon'],
        ])));

        $this->assertSame([$parCompte->number], $this->numeros($this->filtre([
            'customer' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'mariam@'],
        ])));
    }

    /**
     * « Ne contient pas » sur plusieurs champs : aucun ne doit contenir. Sinon
     * une cliente trouvée par son e-mail passerait au motif que son nom de
     * livraison, lui, ne correspond pas.
     */
    public function test_ne_contient_pas_exclut_sur_tous_les_champs(): void
    {
        $compte   = User::factory()->create(['name' => 'Mariam', 'email' => 'mariam@exemple.ci']);
        $mariam   = $this->commande(['user_id' => $compte->id]);
        $invitee  = $this->commande();

        $numeros = $this->numeros($this->filtre([
            'customer' => ['filterType' => 'text', 'type' => 'notContains', 'filter' => 'mariam'],
        ]));

        $this->assertContains($invitee->number, $numeros);
        $this->assertNotContains($mariam->number, $numeros);
    }

    public function test_les_jokers_saisis_ne_ramenent_pas_tout(): void
    {
        $this->commande();

        // « % » tapé dans un champ ne doit pas se comporter comme un joker.
        $this->assertSame([], $this->numeros($this->filtre([
            'phone' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '%'],
        ])));
    }

    public function test_deux_conditions_reliees_par_ou(): void
    {
        $cocody = $this->commande();
        $yop    = $this->commande(['shipping_address' => ['commune' => 'Yopougon', 'city' => 'Abidjan']]);
        $this->commande(['shipping_address' => ['commune' => 'Marcory', 'city' => 'Abidjan']]);

        $numeros = $this->numeros($this->filtre(['locality' => [
            'filterType' => 'text', 'operator' => 'OR',
            'conditions' => [
                ['filterType' => 'text', 'type' => 'equals', 'filter' => 'Cocody'],
                ['filterType' => 'text', 'type' => 'equals', 'filter' => 'Yopougon'],
            ],
        ]]));

        $this->assertEqualsCanonicalizing([$cocody->number, $yop->number], $numeros);
    }

    // ── Nombres et montants ────────────────────────────────────────────────

    public function test_filtrer_sur_le_montant(): void
    {
        $this->commande(['total' => 5000]);
        $grosse = $this->commande(['total' => 45000]);

        $this->assertSame([$grosse->number], $this->numeros($this->filtre([
            'total' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 20000],
        ])));
    }

    /**
     * Le montant est masqué à qui n'a pas `finance.view`. Filtrer dessus
     * permettrait de le retrouver par tâtonnements : le filtre est ignoré.
     */
    public function test_sans_droit_finance_le_filtre_sur_le_montant_est_ignore(): void
    {
        $agent = User::factory()->create();
        $agent->syncRoles(['staff']);
        Sanctum::actingAs($agent);

        $this->commande(['total' => 5000]);
        $this->commande(['total' => 45000]);

        $this->assertCount(2, $this->numeros($this->filtre([
            'total' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 20000],
        ])));
    }

    // ── Tri ────────────────────────────────────────────────────────────────

    public function test_trier_par_montant(): void
    {
        $moyenne = $this->commande(['total' => 20000]);
        $petite  = $this->commande(['total' => 5000]);
        $grosse  = $this->commande(['total' => 45000]);

        $this->assertSame([$petite->number, $moyenne->number, $grosse->number], $this->numeros([
            'sort' => json_encode([['colId' => 'total', 'sort' => 'asc']]),
        ]));
    }

    public function test_une_colonne_inconnue_est_ignoree(): void
    {
        $this->commande();

        // Disposition enregistrée avant qu'une colonne soit renommée, ou
        // modèle forgé : l'écran continue de répondre.
        $this->getJson('/api/admin/orders?' . http_build_query([
            'filter' => json_encode(['id; DROP TABLE orders' => ['type' => 'equals', 'filter' => 1]]),
            'sort'   => json_encode([['colId' => 'secret', 'sort' => 'asc']]),
        ]))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_modele_illisible_ne_filtre_rien(): void
    {
        $this->commande();

        $this->getJson('/api/admin/orders?filter=%7Bpas-du-json')->assertOk()->assertJsonCount(1, 'data');
    }

    // ── Filtres rapides ────────────────────────────────────────────────────

    public function test_plusieurs_statuts_a_la_fois(): void
    {
        $attente = $this->commande(['status' => 'pending']);
        $cours   = $this->commande(['status' => 'processing']);
        $this->commande(['status' => 'delivered']);

        $this->assertEqualsCanonicalizing(
            [$attente->number, $cours->number],
            $this->numeros(['status' => 'pending,processing']),
        );
    }

    /**
     * Régression : le « ou » de la recherche n'était pas groupé, et une
     * recherche combinée à un statut ramenait des commandes de tous les statuts.
     */
    public function test_la_recherche_respecte_le_statut_choisi(): void
    {
        $compte = User::factory()->create(['email' => 'awa@exemple.ci']);
        $this->commande(['status' => 'delivered', 'user_id' => $compte->id]);
        $attente = $this->commande(['status' => 'pending', 'user_id' => $compte->id]);

        $this->assertSame([$attente->number], $this->numeros(['status' => 'pending', 'search' => 'awa@']));
    }

    public function test_les_commandes_mises_de_cote_sont_ecartees_du_decompte(): void
    {
        $gardee = $this->commande();
        $decote = $this->commande();

        $reponse = $this->getJson('/api/admin/orders?' . http_build_query(['exclude' => [$decote->id]]))->assertOk();

        // Écartées côté serveur : le total annoncé ne compte pas une ligne
        // que l'agent ne voit pas.
        $this->assertSame([$gardee->number], collect($reponse->json('data'))->pluck('number')->all());
        $this->assertSame(1, $reponse->json('meta.total'));
    }

    public function test_filtrer_les_payees_et_les_extraites(): void
    {
        $payee    = $this->commande(['paid_at' => now()]);
        $extraite = $this->commande(['exported_at' => now()]);

        $this->assertSame([$payee->number], $this->numeros(['paid' => 1]));
        $this->assertSame([$extraite->number], $this->numeros(['exported' => 1]));
    }

    // ── Recherche de plusieurs commandes ───────────────────────────────────

    public function test_plusieurs_numeros_separes_par_des_virgules(): void
    {
        $une   = $this->commande(['number' => 'ORD-2026-00042']);
        $deux  = $this->commande(['number' => 'ORD-2026-00043']);
        $trois = $this->commande(['number' => 'ORD-2026-00044']);

        $trouves = $this->numeros(['search' => 'ORD-2026-00042, ord-2026-00044', 'per_page' => 100]);

        sort($trouves);
        $this->assertSame([$une->number, $trois->number], $trouves);
    }

    public function test_numeros_separes_par_des_espaces_ou_des_retours_a_la_ligne(): void
    {
        $this->commande(['number' => 'ORD-2026-00042']);
        $this->commande(['number' => 'ORD-2026-00043']);
        $this->commande(['number' => 'ORD-2026-00044']);

        $this->assertCount(2, $this->numeros(['search' => 'ORD-2026-00042 ORD-2026-00043']));
        $this->assertCount(2, $this->numeros(['search' => "ORD-2026-00043\nORD-2026-00044"]));
    }

    public function test_un_nom_ou_un_telephone_avec_des_espaces_reste_une_seule_recherche(): void
    {
        $awa   = $this->commande(['number' => 'ORD-2026-00050']);
        $autre = $this->commande([
            'number' => 'ORD-2026-00051',
            'shipping_address' => ['first_name' => 'Awa', 'last_name' => 'Traoré', 'phone' => '0700000020'],
        ]);

        // « Awa Koné » découpé en deux ramènerait aussi Awa Traoré.
        $this->assertNotContains($autre->number, $this->numeros(['search' => 'Awa Koné']));
        $this->assertSame([], $this->numeros(['search' => '07 00 00 00 20']));
    }

    public function test_les_numeros_absents_sont_signales_avec_leur_raison(): void
    {
        $this->commande(['number' => 'ORD-2026-00042']);
        $this->commande(['number' => 'ORD-2026-00043', 'status' => 'cancelled']);

        $this->getJson('/api/admin/orders?' . http_build_query([
            'search' => 'ORD-2026-00042, ORD-2026-00043, ORD-2026-09999',
            'status' => 'pending',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('search.not_found', ['ORD-2026-09999'])
            // Elle existe : c'est le filtre de statut qui la cache.
            ->assertJsonPath('search.filtered_out', ['ORD-2026-00043']);
    }

    public function test_une_recherche_simple_ne_renvoie_pas_de_bilan(): void
    {
        $this->commande(['number' => 'ORD-2026-00042']);

        $this->getJson('/api/admin/orders?search=ORD-2026-00042')
            ->assertOk()
            ->assertJsonMissingPath('search');
    }
}
