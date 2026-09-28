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
 * Le retour de tournée : ce qui a été livré, ce qui revient, et ce que le
 * livreur rapporte.
 *
 * C'est la moitié du travail que la feuille de papier ne sait pas faire. Les
 * règles vérifiées ici tiennent toutes à une seule question — l'écart de caisse
 * affiché veut-il dire quelque chose ? Une commande revenue mais toujours
 * « expédiée », une tournée clôturée à moitié pointée, ou un pointage rejoué
 * après clôture le rendent illisible.
 */
class DeliveryRoundPointingTest extends TestCase
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

    /** Commande à régler à la porte : c'est le cas qui fait vivre l'encaissement. */
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

    /** @return array{0: string, 1: array<int, array<string, mixed>>} */
    private function tournee(array $orders): array
    {
        $reponse = $this->postJson('/api/admin/delivery-rounds', [
            'order_ids' => collect($orders)->pluck('id')->all(),
        ])->assertCreated();

        return [$reponse->json('data.code'), $reponse->json('data.lines')];
    }

    public function test_pointer_livre_marque_la_commande_livree_et_encaisse_le_montant_attendu(): void
    {
        $order = $this->commandeCod(12000);
        [$code, $lignes] = $this->tournee([$order]);

        $reponse = $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status'         => 'delivered',
            'payment_method' => 'cash',
        ])->assertOk();

        $this->assertSame('delivered', $reponse->json('data.lines.0.status'));
        // Rien saisi : le montant encaissé vaut le montant attendu.
        $this->assertEqualsWithDelta(12000, $reponse->json('data.lines.0.amount_collected'), 0.001);
        $this->assertEqualsWithDelta(0, $reponse->json('data.summary.difference'), 0.001);

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    /** L'écart de caisse est la seule ligne que la gérante regarde vraiment. */
    public function test_un_montant_inferieur_fait_apparaitre_lecart(): void
    {
        $order = $this->commandeCod(12000);
        [$code, $lignes] = $this->tournee([$order]);

        $reponse = $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status'           => 'delivered',
            'amount_collected' => 10000,
        ])->assertOk();

        $this->assertEqualsWithDelta(-2000, $reponse->json('data.summary.difference'), 0.001);
    }

    /**
     * Une commande revenue en boutique doit réapparaître là où on compose les
     * tournées. La laisser « expédiée » la ferait disparaître de tous les écrans.
     */
    public function test_un_echec_ramene_la_commande_en_preparation_avec_son_motif(): void
    {
        $order = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$order]);

        $reponse = $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
            'reason' => 'Cliente absente, ne répond pas au téléphone.',
        ])->assertOk();

        $this->assertSame('failed', $reponse->json('data.lines.0.status'));
        $this->assertSame(
            'Cliente absente, ne répond pas au téléphone.',
            $reponse->json('data.lines.0.failure_reason'),
        );
        // Rien n'est encaissé sur un passage qui a échoué.
        $this->assertEqualsWithDelta(0, $reponse->json('data.lines.0.amount_collected'), 0.001);

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_un_echec_sans_motif_est_refuse(): void
    {
        $order = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$order]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    /**
     * Une commande revenue puis repointée doit pouvoir repartir : c'est tout
     * l'intérêt d'avoir une ligne par passage plutôt qu'une colonne écrasée.
     */
    public function test_une_commande_echouee_peut_repartir_dans_une_autre_tournee(): void
    {
        $order = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$order]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
            'reason' => 'Adresse introuvable.',
        ])->assertOk();

        [$second] = $this->tournee([$order]);

        $this->assertNotSame($code, $second);
        $this->assertSame(2, DeliveryRoundOrder::where('order_id', $order->id)->count());
    }

    public function test_la_cloture_refuse_tant_quune_livraison_nest_pas_pointee(): void
    {
        $a = $this->commandeCod();
        $b = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$a, $b]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();

        $this->postJson("/api/admin/delivery-rounds/{$code}/close")
            ->assertStatus(422)
            ->assertJsonPath('code', 'round_not_pointed')
            ->assertJsonPath('remaining', 1);

        $this->assertSame(
            DeliveryRound::STATUS_DISPATCHED,
            DeliveryRound::where('code', $code)->value('status'),
        );
    }

    public function test_la_cloture_arrete_les_comptes(): void
    {
        $a = $this->commandeCod(10000);
        $b = $this->commandeCod(5000);
        [$code, $lignes] = $this->tournee([$a, $b]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();
        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[1]['id']}", [
            'status' => 'postponed',
            'reason' => 'Cliente en déplacement, à représenter lundi.',
        ])->assertOk();

        $reponse = $this->postJson("/api/admin/delivery-rounds/{$code}/close")->assertOk();

        $this->assertSame(DeliveryRound::STATUS_CLOSED, $reponse->json('data.status'));
        $this->assertNotNull($reponse->json('data.closed_at'));
        $this->assertSame(1, $reponse->json('data.summary.delivered'));
        $this->assertSame(1, $reponse->json('data.summary.postponed'));
        $this->assertEqualsWithDelta(10000, $reponse->json('data.summary.collected'), 0.001);
    }

    /** Un compte arrêté ne se rouvre pas : la caisse a déjà été comptée. */
    public function test_une_tournee_cloturee_naccepte_plus_de_pointage(): void
    {
        $order = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$order]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();
        $this->postJson("/api/admin/delivery-rounds/{$code}/close")->assertOk();

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'failed',
            'reason' => 'Erreur de saisie.',
        ])->assertStatus(422)->assertJsonPath('code', 'round_closed');

        $this->postJson("/api/admin/delivery-rounds/{$code}/close")
            ->assertStatus(422)
            ->assertJsonPath('code', 'round_closed');
    }

    public function test_une_ligne_dune_autre_tournee_est_refusee(): void
    {
        [$premier, $lignesA] = $this->tournee([$this->commandeCod()]);
        [$second]            = $this->tournee([$this->commandeCod()]);

        $this->patchJson("/api/admin/delivery-rounds/{$second}/lines/{$lignesA[0]['id']}", [
            'status' => 'delivered',
        ])->assertNotFound();
    }

    public function test_le_pointage_retient_qui_la_saisi(): void
    {
        $order = $this->commandeCod();
        [$code, $lignes] = $this->tournee([$order]);

        $this->patchJson("/api/admin/delivery-rounds/{$code}/lines/{$lignes[0]['id']}", [
            'status' => 'delivered',
        ])->assertOk();

        $this->assertSame(
            $this->admin->id,
            DeliveryRoundOrder::find($lignes[0]['id'])->confirmed_by,
        );
    }
}
