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
 * Expédition en masse, hors tournée.
 *
 * Le risque propre à ce geste : être plus permissif que l'expédition à
 * l'unité. Vingt commandes passées d'un clic laisseraient alors partir vingt
 * fois ce que l'écran de détail refuse une fois — une commande impayée, ou une
 * commande dont les frais hors zone n'ont jamais été fixés.
 */
class BulkShipTest extends TestCase
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

    public function test_marque_les_commandes_comme_expediees(): void
    {
        $a = $this->commandePrete();
        $b = $this->commandePrete();

        $this->postJson('/api/admin/orders/bulk-ship', ['order_ids' => [$a->id, $b->id]])
            ->assertOk()
            ->assertJsonCount(2, 'shipped')
            ->assertJsonCount(0, 'rejected');

        $this->assertSame('shipped', $a->fresh()->status);
        $this->assertSame('shipped', $b->fresh()->status);
        $this->assertNotNull($b->fresh()->shipped_at);
    }

    public function test_les_garde_fous_de_lexpedition_unitaire_sappliquent(): void
    {
        $bonne   = $this->commandePrete();
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        $reponse = $this->postJson('/api/admin/orders/bulk-ship', [
            'order_ids' => [$bonne->id, $impayee->id],
        ])->assertOk();

        $this->assertSame($bonne->number,   $reponse->json('shipped.0.number'));
        $this->assertSame($impayee->number, $reponse->json('rejected.0.number'));
        $this->assertTrue($reponse->json('rejected.0.forceable'));

        $this->assertSame('processing', $impayee->fresh()->status);
    }

    public function test_force_leve_les_garde_fous(): void
    {
        $impayee = $this->commandePrete(['paid_at' => null, 'payment_method' => 'wave']);

        $this->postJson('/api/admin/orders/bulk-ship', [
            'order_ids' => [$impayee->id],
            'force'     => true,
        ])->assertOk()->assertJsonCount(1, 'shipped');

        $this->assertSame('shipped', $impayee->fresh()->status);
    }

    /** Une commande annulée n'est pas un refus qu'on lève : c'est une erreur de sélection. */
    public function test_une_commande_annulee_reste_refusee_meme_en_force(): void
    {
        $annulee = Order::factory()->cancelled()->create();

        $reponse = $this->postJson('/api/admin/orders/bulk-ship', [
            'order_ids' => [$annulee->id],
            'force'     => true,
        ])->assertOk();

        $this->assertFalse($reponse->json('rejected.0.forceable'));
        $this->assertSame('cancelled', $annulee->fresh()->status);
    }

    /**
     * Une commande déjà expédiée n'est ni un succès à annoncer ni un refus à
     * signaler : la compter comme écartée ferait croire à un problème.
     */
    public function test_une_commande_deja_expediee_est_ignoree_sans_bruit(): void
    {
        $deja = $this->commandePrete(['status' => 'shipped', 'shipped_at' => now()->subDay()]);

        $this->postJson('/api/admin/orders/bulk-ship', ['order_ids' => [$deja->id]])
            ->assertOk()
            ->assertJsonCount(0, 'shipped')
            ->assertJsonCount(0, 'rejected');
    }

    public function test_un_agent_sans_permission_ne_peut_pas_expedier_en_masse(): void
    {
        $intrus = User::factory()->create();
        $intrus->syncRoles(['customer']);
        Sanctum::actingAs($intrus);

        $this->postJson('/api/admin/orders/bulk-ship', ['order_ids' => [$this->commandePrete()->id]])
            ->assertForbidden();
    }
}
