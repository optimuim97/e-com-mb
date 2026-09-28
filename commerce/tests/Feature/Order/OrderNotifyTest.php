<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Services\CustomerWhatsAppService;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Order\Services\OrderNotificationMessage;
use Tests\TestCase;

/**
 * Renvoi manuel du récapitulatif de commande depuis l'écran de traitement.
 *
 * Sert de recours quand l'envoi automatique échoue : la réponse doit dire
 * précisément ce qui est parti, pour que l'interface puisse proposer à
 * l'agent d'envoyer lui-même.
 */
class OrderNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    /** Agent habilité à traiter les commandes. */
    private function agent(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['staff']);
        return $user;
    }

    /** Cliente ordinaire, sans accès à l'administration. */
    private function cliente(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['customer']);
        return $user;
    }

    private function commande(array $attrs = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'number'           => 'CMD-TEST-001',
            'status'           => 'pending',
            'subtotal'         => 20000,
            'discount_amount'  => 2000,
            'shipping_cost'    => 1000,
            'total'            => 19000,
            'shipping_address' => [
                'first_name'    => 'Awa',
                'phone'         => '2250707849883',
                'address_line1' => 'Cocody',
                'commune'       => 'Angré',
            ],
        ], $attrs));

        OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => 'Eau de rose',
            'quantity'     => 2,
            'unit_price'   => 10000,
            'total_price'  => 20000,
        ]);

        return $order->fresh(['items']);
    }

    public function test_le_message_detaille_les_articles_et_les_totaux(): void
    {
        $message = app(OrderNotificationMessage::class)->build($this->commande());

        $this->assertStringContainsString('Awa', $message);
        $this->assertStringContainsString('CMD-TEST-001', $message);
        // Le détail doit figurer dans le texte, pas seulement dans le PDF.
        $this->assertStringContainsString('Eau de rose × 2', $message);
        $this->assertStringContainsString('20 000 F CFA', $message);
        $this->assertStringContainsString('Remise : −2 000 F CFA', $message);
        $this->assertStringContainsString('Livraison : 1 000 F CFA', $message);
        $this->assertStringContainsString('Total : 19 000 F CFA', $message);
        $this->assertStringContainsString('Adresse : Cocody, Angré', $message);
    }

    public function test_les_lignes_a_zero_sont_omises(): void
    {
        // Une ligne « Remise : 0 F CFA » n'apprend rien et alourdit un
        // message lu sur téléphone.
        $message = app(OrderNotificationMessage::class)->build(
            $this->commande(['discount_amount' => 0, 'shipping_cost' => 0])
        );

        $this->assertStringNotContainsString('Remise', $message);
        $this->assertStringNotContainsString('Livraison :', $message);
        // L'adresse, elle, reste affichée.
        $this->assertStringContainsString('Adresse :', $message);
    }

    public function test_envoi_reussi_signale_le_texte_et_la_facture(): void
    {
        $faux = $this->mock(CustomerWhatsAppService::class);
        $faux->shouldReceive('sendSessionOrTemplate')->once()->andReturn(true);
        $faux->shouldReceive('sendDocument')->once()->andReturn(true);

        $order = $this->commande();

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/admin/orders/{$order->id}/notify")
            ->assertOk()
            ->assertJson(['sent' => true, 'document' => true]);
    }

    public function test_echec_whatsapp_renvoie_502_avec_le_message_a_envoyer_soi_meme(): void
    {
        // C'est tout l'intérêt du recours : l'agent doit récupérer le texte
        // pour l'envoyer lui-même via wa.me.
        $faux = $this->mock(CustomerWhatsAppService::class);
        $faux->shouldReceive('sendSessionOrTemplate')->once()->andReturn(false);
        $faux->shouldReceive('sendDocument')->andReturn(false);

        $order = $this->commande();

        Sanctum::actingAs($this->agent());

        $res = $this->postJson("/api/admin/orders/{$order->id}/notify")
            ->assertStatus(502)
            ->assertJson(['sent' => false]);

        $this->assertStringContainsString('Eau de rose', $res->json('message'));
        $this->assertSame('2250707849883', $res->json('phone'));
    }

    public function test_commande_sans_telephone_est_refusee_clairement(): void
    {
        $order = $this->commande(['shipping_address' => ['first_name' => 'Awa']]);

        Sanctum::actingAs($this->agent());

        $this->postJson("/api/admin/orders/{$order->id}/notify")
            ->assertStatus(422)
            ->assertJson(['sent' => false, 'phone' => null]);
    }

    public function test_un_agent_sans_droit_ne_peut_pas_notifier(): void
    {
        $order = $this->commande();

        Sanctum::actingAs($this->cliente());

        $this->postJson("/api/admin/orders/{$order->id}/notify")
            ->assertForbidden();
    }
}
