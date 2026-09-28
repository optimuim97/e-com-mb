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
 * L'espace du livreur.
 *
 * Le pointage se fait désormais sur le terrain. Ce qui est vérifié ici est
 * moins le geste — il est partagé avec l'écran de la boutique — que sa
 * portée : un livreur ne voit et ne touche que les tournées à son nom. La
 * permission dit qu'on peut pointer, elle ne dit pas lesquelles ; c'est le
 * filtre sur `courier_id` qui le dit, et lui seul.
 */
class CourierRoundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $livreur;
    private User $autreLivreur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);

        $this->livreur = User::factory()->create(['name' => 'Yao Kouassi']);
        $this->livreur->syncRoles(['livreur']);

        $this->autreLivreur = User::factory()->create(['name' => 'Ali Traoré']);
        $this->autreLivreur->syncRoles(['livreur']);
    }

    private function commandeCod(int $total = 12000): Order
    {
        return Order::factory()->create([
            'status'         => 'processing',
            'paid_at'        => null,
            'payment_method' => 'cod',
            'shipping_cost'  => 1500,
            'total'          => $total,
        ]);
    }

    /** Tournée préparée par la boutique et confiée à un livreur. */
    private function tourneePour(User $livreur, array $orders): array
    {
        Sanctum::actingAs($this->admin);

        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids'  => collect($orders)->pluck('id')->all(),
            'courier_id' => $livreur->id,
        ])->assertCreated();

        return [$reponse->json('data.code'), $reponse->json('data.lines')];
    }

    /**
     * La connexion livreur se fait par e-mail (décision du 29 août).
     *
     * Ce que le test garde, ce n'est pas le mot de passe : c'est la charge
     * utile. L'écran `/livreur` s'ouvre sur `can('rounds.point')`, et cette
     * permission ne remonte que si la relation `roles` est chargée à la
     * connexion. Si elle cessait de l'être, le livreur se connecterait
     * correctement puis serait renvoyé vers la boutique, sans rien pour
     * comprendre pourquoi.
     */
    public function test_le_livreur_se_connecte_par_email_et_recoit_de_quoi_ouvrir_son_espace(): void
    {
        $this->livreur->update([
            'email'    => 'yao@boutique.local',
            'password' => bcrypt('motdepasse'),
        ]);

        $reponse = $this->postJson('/api/auth/login', [
            'email'    => 'yao@boutique.local',
            'password' => 'motdepasse',
        ])->assertOk();

        $this->assertContains('livreur',      $reponse->json('user.roles'));
        $this->assertContains('rounds.point', $reponse->json('user.permissions'));

        // Et rien de plus : il n'entre pas dans le back-office.
        $this->assertFalse($reponse->json('user.is_staff'));
        $this->assertFalse($reponse->json('user.is_admin'));
        $this->assertSame(['rounds.point'], $reponse->json('user.permissions'));
    }

    public function test_le_livreur_ne_voit_que_ses_tournees(): void
    {
        [$sienne] = $this->tourneePour($this->livreur,      [$this->commandeCod()]);
        [$autre]  = $this->tourneePour($this->autreLivreur, [$this->commandeCod()]);

        Sanctum::actingAs($this->livreur);

        $codes = collect($this->getJson('/api/livreur/rounds')->assertOk()->json('data'))
            ->pluck('code');

        $this->assertContains($sienne, $codes);
        $this->assertNotContains($autre, $codes);
    }

    /**
     * Un code deviné ne doit rien ouvrir — et un 404 plutôt qu'un 403 : le
     * livreur n'a pas à apprendre qu'une tournée existe ailleurs.
     */
    public function test_la_tournee_dun_autre_livreur_est_introuvable(): void
    {
        [$autre, $lignes] = $this->tourneePour($this->autreLivreur, [$this->commandeCod()]);

        Sanctum::actingAs($this->livreur);

        $this->getJson("/api/livreur/rounds/{$autre}")->assertNotFound();

        $this->patchJson("/api/livreur/rounds/{$autre}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertNotFound();

        // Et la ligne n'a pas bougé.
        $this->assertSame(
            DeliveryRoundOrder::STATUS_PENDING,
            DeliveryRoundOrder::find($lignes[0]['id'])->status,
        );
    }

    public function test_le_livreur_pointe_une_livraison_sur_le_terrain(): void
    {
        $order = $this->commandeCod(12000);
        [$code, $lignes] = $this->tourneePour($this->livreur, [$order]);

        Sanctum::actingAs($this->livreur);

        $reponse = $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status'         => 'delivered',
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame('delivered', $reponse->json('data.lines.0.status'));
        $this->assertEqualsWithDelta(12000, $reponse->json('data.summary.collected'), 0.001);

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame($this->livreur->id, DeliveryRoundOrder::find($lignes[0]['id'])->confirmed_by);
    }

    /** Même règle qu'à la boutique : un échec sans motif ne se rattrape pas. */
    public function test_un_echec_sans_motif_est_refuse(): void
    {
        [$code, $lignes] = $this->tourneePour($this->livreur, [$this->commandeCod()]);

        Sanctum::actingAs($this->livreur);

        $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    /**
     * « L'agent pointe, et le livreur aussi » : les deux chemins mènent au même
     * enregistrement. L'agent doit pouvoir corriger une saisie faite en course.
     */
    public function test_lagent_peut_corriger_un_pointage_fait_par_le_livreur(): void
    {
        $order = $this->commandeCod(12000);
        [$code, $lignes] = $this->tourneePour($this->livreur, [$order]);

        Sanctum::actingAs($this->livreur);
        $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();

        Sanctum::actingAs($this->admin);
        $reponse = $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status'           => 'delivered',
            'amount_collected' => 10000,
        ])->assertOk();

        $this->assertEqualsWithDelta(-2000, $reponse->json('data.summary.difference'), 0.001);
        $this->assertSame($this->admin->id, DeliveryRoundOrder::find($lignes[0]['id'])->confirmed_by);
    }

    /** La clôture reste à la boutique : arrêter les comptes, c'est compter la caisse. */
    public function test_le_livreur_ne_peut_pas_cloturer(): void
    {
        [$code, $lignes] = $this->tourneePour($this->livreur, [$this->commandeCod()]);

        Sanctum::actingAs($this->livreur);
        $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();

        $this->postJson("/api/admin/delivery-rounds/{$code}/close")->assertForbidden();

        $this->assertSame(
            DeliveryRound::STATUS_DISPATCHED,
            DeliveryRound::where('code', $code)->value('status'),
        );
    }

    public function test_une_tournee_cloturee_nest_plus_modifiable_par_le_livreur(): void
    {
        [$code, $lignes] = $this->tourneePour($this->livreur, [$this->commandeCod()]);

        Sanctum::actingAs($this->livreur);
        $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/delivery-rounds/{$code}/close")->assertOk();

        Sanctum::actingAs($this->livreur);
        $this->patchJson("/api/livreur/rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
            'reason' => 'Erreur.',
        ])->assertStatus(422)->assertJsonPath('code', 'round_closed');
    }

    /** Le livreur n'entre pas dans le back-office. */
    public function test_le_livreur_na_pas_acces_a_ladministration(): void
    {
        Sanctum::actingAs($this->livreur);

        $this->getJson('/api/admin/delivery-rounds')->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_une_cliente_na_pas_acces_a_lespace_livreur(): void
    {
        $cliente = User::factory()->create();
        $cliente->syncRoles(['customer']);

        Sanctum::actingAs($cliente);
        $this->getJson('/api/livreur/rounds')->assertForbidden();
    }
}
