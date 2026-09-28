<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Préparation d'une tournée : le geste qui remplace « je télécharge la feuille
 * puis je passe les commandes en expédiée une par une ».
 *
 * Ce qui est vérifié ici tient à la question du retour de tournée : « qu'est-ce
 * qui est parti, avec qui, et combien doit-il rapporter ? ». Un code qu'on ne
 * retrouve pas, une commande impayée embarquée sans le dire, ou deux livreurs
 * envoyés à la même adresse rendent cette question sans réponse.
 */
class DeliveryRoundTest extends TestCase
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

    /** Commande abidjanaise réglée : rien ne s'oppose à son expédition. */
    private function commandePrete(array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status'        => 'processing',
            'paid_at'       => now(),
            'shipping_cost' => 1500,
            'total'         => 11500,
        ], $overrides));
    }

    public function test_creer_une_tournee_expedie_les_commandes_et_rend_un_code(): void
    {
        $a = $this->commandePrete();
        $b = $this->commandePrete();

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$a->id, $b->id],
            'label'     => 'Cocody — matin',
        ])->assertCreated();

        $code = $reponse->json('data.code');

        $this->assertMatchesRegularExpression('/^TR-\d{4}-[A-Z]+$/', $code);
        $this->assertSame(2, $reponse->json('data.orders_count'));

        $this->assertSame('shipped', $a->fresh()->status);
        $this->assertSame('shipped', $b->fresh()->status);
        $this->assertNotNull($a->fresh()->shipped_at);
    }

    public function test_le_code_est_unique_dans_la_journee(): void
    {
        $premier = DeliveryRound::generateCode();
        DeliveryRound::create(['code' => $premier, 'status' => DeliveryRound::STATUS_DISPATCHED]);

        $this->assertNotSame($premier, DeliveryRound::generateCode());
    }

    /**
     * Le montant attendu est ce que le livreur rapporte, pas le prix de la
     * commande : une commande déjà réglée compte pour zéro.
     */
    public function test_montant_attendu_ignore_les_commandes_deja_payees(): void
    {
        $payee   = $this->commandePrete(['total' => 20000]);
        $aRegler = $this->commandePrete([
            'total'          => 15000,
            'paid_at'        => null,
            'payment_method' => 'cod',
        ]);

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$payee->id, $aRegler->id],
        ])->assertCreated();

        $this->assertEqualsWithDelta(15000, $reponse->json('data.expected_total'), 0.001);
    }

    /**
     * Le garde-fou de l'expédition unitaire vaut pour l'expédition en masse.
     * Une commande impayée hors paiement à la livraison reste à quai, et
     * l'agent apprend pourquoi.
     */
    public function test_une_commande_impayee_est_ecartee_avec_son_motif(): void
    {
        $bonne   = $this->commandePrete();
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$bonne->id, $impayee->id],
        ])->assertCreated();

        $this->assertSame(1, $reponse->json('data.orders_count'));
        $this->assertSame($impayee->number, $reponse->json('rejected.0.number'));
        $this->assertStringContainsString("n'est pas payée", $reponse->json('rejected.0.reasons.0'));

        $this->assertSame('processing', $impayee->fresh()->status);
    }

    public function test_aucune_commande_expediable_renvoie_422_avec_les_motifs(): void
    {
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$impayee->id]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_shippable_orders')
            ->assertJsonPath('rejected.0.number', $impayee->number);

        $this->assertDatabaseCount('delivery_rounds', 0);
    }

    /** `force` reprend l'échappatoire de l'expédition unitaire. */
    public function test_force_embarque_malgre_les_garde_fous(): void
    {
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$impayee->id],
            'force'     => true,
        ])->assertCreated();

        $this->assertSame('shipped', $impayee->fresh()->status);
    }

    /**
     * Deux livreurs à la même adresse le même jour : la cliente est livrée
     * deux fois, ou personne ne sait qui a encaissé.
     */
    public function test_une_commande_deja_en_tournee_ouverte_est_refusee(): void
    {
        $order = $this->commandePrete();

        $premiere = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->assertCreated();

        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->assertStatus(422)
            ->assertJsonPath(
                'rejected.0.reasons.0',
                'Déjà dans la tournée ' . $premiere->json('data.code') . '.',
            );
    }

    public function test_une_commande_annulee_ne_part_pas_meme_en_force(): void
    {
        $annulee = Order::factory()->cancelled()->create(['paid_at' => now()]);

        $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$annulee->id],
            'force'     => true,
        ])->assertStatus(422)
          ->assertJsonPath('rejected.0.reasons.0', 'Commande annulée.');
    }

    /**
     * Le front n'offre « expédier quand même » que sur les refus qui se lèvent.
     * Sans ce drapeau, il proposerait de forcer une commande annulée et
     * rejouerait le même échec.
     */
    public function test_les_refus_distinguent_ce_qui_peut_etre_force(): void
    {
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);
        $annulee = Order::factory()->cancelled()->create();

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => [$impayee->id, $annulee->id],
        ])->assertStatus(422);

        $refus = collect($reponse->json('rejected'))->keyBy('number');

        $this->assertTrue($refus[$impayee->number]['forceable']);
        $this->assertFalse($refus[$annulee->number]['forceable']);
    }

    public function test_retrouver_une_tournee_par_son_code_avec_ou_sans_tirets(): void
    {
        $order = $this->commandePrete();
        $code  = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->json('data.code');

        $this->getJson('/api/admin/delivery-rounds/' . $code)
            ->assertOk()
            ->assertJsonPath('data.code', $code);

        // Recopié d'une feuille froissée, sans les tirets.
        $this->getJson('/api/admin/delivery-rounds/' . str_replace('-', '', $code))
            ->assertOk()
            ->assertJsonPath('data.code', $code);

        $this->getJson('/api/admin/delivery-rounds/TR-0101-Z')->assertNotFound();
    }

    /** La feuille imprimée porte le code : sans lui, le papier est anonyme. */
    public function test_la_feuille_de_route_porte_le_code_et_le_montant(): void
    {
        $order = $this->commandePrete([
            'paid_at'          => null,
            'payment_method'   => 'cod',
            'total'            => 12000,
            'shipping_address' => [
                'first_name'    => 'Awa',
                'last_name'     => 'Traoré',
                'address_line1' => 'Rue des Jardins',
                'city'          => 'Abidjan',
                'commune'       => 'Cocody',
                'country'       => 'CI',
                'phone'         => '0708091011',
            ],
        ]);

        $code = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->json('data.code');

        $reponse = $this->get('/api/admin/delivery-rounds/' . $code . '/sheet')->assertOk();
        $texte   = $reponse->getContent();

        // Libellés sans accent ni alignement décoratif depuis que la feuille
        // tient sur 48 colonnes, largeur native d'une imprimante thermique.
        $this->assertStringContainsString('Tournee : ' . $code, $texte);
        $this->assertStringContainsString('A encaisser', $texte);
        // Le livreur appelle avant de se présenter : le numéro doit y être.
        $this->assertStringContainsString('0708091011', $texte);
        $reponse->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_la_tournee_enregistre_ses_lignes_en_attente(): void
    {
        $a = $this->commandePrete();
        $b = $this->commandePrete();

        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$a->id, $b->id]])
            ->assertCreated();

        $lignes = DeliveryRoundOrder::orderBy('position')->get();

        $this->assertCount(2, $lignes);
        $this->assertSame([1, 2], $lignes->pluck('position')->all());
        $this->assertSame(
            [DeliveryRoundOrder::STATUS_PENDING, DeliveryRoundOrder::STATUS_PENDING],
            $lignes->pluck('status')->all(),
        );
    }

    // ── Ce que le livreur emporte ──────────────────────────────────────────

    private function avecArticles(Order $order, array $lignes): Order
    {
        foreach ($lignes as [$nom, $qte]) {
            \Modules\Order\Models\OrderItem::create([
                'order_id' => $order->id, 'product_name' => $nom, 'quantity' => $qte,
                'unit_price' => 5000, 'total_price' => 5000 * $qte,
            ]);
        }

        return $order;
    }

    /**
     * Douze livraisons peuvent faire douze pots ou quarante : c'est le second
     * nombre qui dit si tout tient sur la moto.
     */
    public function test_la_tournee_compte_les_articles_a_emporter(): void
    {
        $a = $this->avecArticles($this->commandePrete(), [['Crème hydratante', 2], ['Sérum', 1]]);
        $b = $this->avecArticles($this->commandePrete(), [['Crème hydratante', 3]]);

        $code = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$a->id, $b->id]])
            ->assertCreated()
            ->assertJsonPath('data.items_count', 6)
            ->json('data.code');

        // La liste l'agrège par sous-requête, le détail le recompte : les deux
        // doivent tomber d'accord.
        $ligne = collect($this->getJson('/api/admin/delivery-rounds')->json('data'))->firstWhere('code', $code);
        $this->assertSame(6, $ligne['items_count']);

        $detail = $this->getJson("/api/admin/delivery-rounds/{$code}")->assertOk()->json('data');
        $this->assertSame(6, $detail['items_count']);
        $this->assertSame([3, 3], collect($detail['lines'])->pluck('items_count')->all());

        // Le chargement, article par article, du plus nombreux au moins nombreux.
        $this->assertSame(
            [['name' => 'Crème hydratante', 'quantity' => 5], ['name' => 'Sérum', 'quantity' => 1]],
            $detail['loading_list'],
        );
    }

    public function test_la_feuille_de_route_annonce_ce_quil_faut_emporter(): void
    {
        $order = $this->avecArticles($this->commandePrete(), [['Crème hydratante', 4]]);

        $code = $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$order->id]])
            ->json('data.code');

        $texte = $this->get("/api/admin/delivery-rounds/{$code}/sheet")->assertOk()->getContent();

        $this->assertStringContainsString('A emporter : 4 articles, 1 colis', $texte);
    }

    /**
     * Un brouillon peut porter un livreur avant son départ. Le lui montrer,
     * c'était lui permettre de pointer livrée une commande encore en boutique.
     */
    public function test_le_livreur_ne_voit_pas_une_tournee_pas_encore_partie(): void
    {
        $livreur = User::factory()->create();
        $livreur->syncRoles(['livreur']);

        $order = $this->commandePrete();
        $code  = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids'  => [$order->id],
            'courier_id' => $livreur->id,
            'draft'      => true,
        ])->assertCreated()->json('data.code');

        Sanctum::actingAs($livreur);

        $this->assertSame([], $this->getJson('/api/livreur/rounds')->assertOk()->json('data'));
        $this->getJson("/api/livreur/rounds/{$code}")->assertNotFound();
    }

    public function test_un_agent_sans_permission_ne_prepare_pas_de_tournee(): void
    {
        $intrus = User::factory()->create();
        $intrus->syncRoles(['customer']);
        Sanctum::actingAs($intrus);

        $this->postJson('/api/admin/delivery-rounds', ['order_ids' => [$this->commandePrete()->id]])
            ->assertForbidden();
    }
}
