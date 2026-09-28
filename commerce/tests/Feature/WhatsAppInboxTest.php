<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppMessage;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * La file WhatsApp n'a de valeur que si son décompte est exact : un message
 * compté deux fois, ou une commande créée sans que le message sorte de la
 * file, et la double saisie ne prouve plus rien.
 */
class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-app-meta-pour-les-tests';

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.whatsapp.app_secret' => self::SECRET]);

        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        // Le rôle staff porte whatsapp.view et whatsapp.convert : c'est
        // l'agent, pas l'admin, qui vide la file au quotidien.
        $this->agent = User::factory()->create();
        $this->agent->syncRoles(['staff']);
    }

    public function test_un_message_entrant_atterrit_dans_la_file(): void
    {
        $this->postWebhook($this->payload('wamid.aaa', 'Bonjour, je veux 2 eau de rose'))->assertOk();

        $message = WhatsAppMessage::firstWhere('wa_message_id', 'wamid.aaa');

        $this->assertNotNull($message);
        $this->assertSame(WhatsAppMessage::STATUS_PENDING, $message->status);
        $this->assertSame('2250707849883', $message->from_phone);
        $this->assertSame('Awa', $message->contact_name);
        $this->assertSame('Bonjour, je veux 2 eau de rose', $message->body);
    }

    public function test_un_evenement_rejoue_ne_cree_pas_de_doublon(): void
    {
        // Meta rejoue tant qu'il n'a pas reçu de 2xx : sans idempotence, la
        // même commande serait saisie deux fois.
        $payload = $this->payload('wamid.bbb', 'Je veux une huile');

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertSame(1, WhatsAppMessage::where('wa_message_id', 'wamid.bbb')->count());
    }

    public function test_la_legende_d_une_photo_est_conservee(): void
    {
        // Une cliente envoie souvent la photo du produit avec sa demande :
        // ignorer la légende reviendrait à perdre la commande.
        $payload = ['entry' => [['changes' => [['value' => [
            'messages' => [[
                'from'  => '2250707849883',
                'type'  => 'image',
                'id'    => 'wamid.ccc',
                'image' => ['caption' => 'Je veux celui-ci en 50 ml'],
            ]],
        ]]]]]];

        $this->postWebhook($payload)->assertOk();

        $message = WhatsAppMessage::firstWhere('wa_message_id', 'wamid.ccc');
        $this->assertSame('Je veux celui-ci en 50 ml', $message->body);
        $this->assertSame('image', $message->type);
    }

    public function test_la_conversion_en_commande_sort_le_message_de_la_file(): void
    {
        Sanctum::actingAs($this->agent);

        $produit = Product::factory()->create(['price' => 5000, 'stock' => 10]);
        $message = $this->messageEnAttente();

        $this->postJson('/api/admin/pos/orders', [
            'customer'            => ['name' => 'Awa Koffi', 'phone' => '0707849883'],
            'items'               => [['product_id' => $produit->id, 'quantity' => 2]],
            'payment_status'      => 'paid',
            'payment_method'      => 'cash',
            'whatsapp_message_id' => $message->id,
        ])->assertCreated();

        $message->refresh();
        $this->assertSame(WhatsAppMessage::STATUS_CONVERTED, $message->status);
        $this->assertNotNull($message->order_id);
        $this->assertSame($this->agent->id, $message->handled_by);

        // L'origine reste lisible sur la commande, sans quoi la part réelle
        // de WhatsApp dans le chiffre serait perdue.
        $this->assertSame('whatsapp', Order::find($message->order_id)->metadata['channel']);
    }

    public function test_une_commande_de_comptoir_reste_marquee_pos(): void
    {
        Sanctum::actingAs($this->agent);

        $produit = Product::factory()->create(['price' => 5000, 'stock' => 10]);

        $reponse = $this->postJson('/api/admin/pos/orders', [
            'customer'       => ['name' => 'Client Comptoir', 'phone' => '0700000009'],
            'items'          => [['product_id' => $produit->id, 'quantity' => 1]],
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ])->assertCreated();

        $order = Order::where('number', $reponse->json('number'))->first();
        $this->assertSame('pos', $order->metadata['channel']);
    }

    public function test_un_message_ecarte_peut_revenir_dans_la_file(): void
    {
        Sanctum::actingAs($this->agent);
        $message = $this->messageEnAttente();

        $this->postJson("/api/admin/whatsapp/messages/{$message->id}/ignore", ['reason' => 'Demande d\'information'])
            ->assertOk()
            ->assertJsonPath('status', WhatsAppMessage::STATUS_IGNORED);

        $this->postJson("/api/admin/whatsapp/messages/{$message->id}/reopen")
            ->assertOk()
            ->assertJsonPath('status', WhatsAppMessage::STATUS_PENDING);

        $this->assertNull($message->fresh()->handling_note);
    }

    public function test_la_reconciliation_compte_les_messages_traites(): void
    {
        Sanctum::actingAs($this->agent);

        $this->messageEnAttente('wamid.r1');
        $this->messageEnAttente('wamid.r2')->update([
            'status'     => WhatsAppMessage::STATUS_IGNORED,
            'handled_at' => now(),
        ]);

        $this->getJson('/api/admin/whatsapp/reconciliation')
            ->assertOk()
            ->assertJsonPath('totaux.messages_recus', 2)
            ->assertJsonPath('totaux.traites', 1)
            ->assertJsonPath('totaux.en_attente', 1)
            ->assertJsonPath('totaux.taux_traitement', fn ($taux) => (float) $taux === 50.0);
    }

    public function test_la_file_est_fermee_a_qui_n_a_pas_la_permission(): void
    {
        $cliente = User::factory()->create();
        $cliente->syncRoles(['customer']);

        Sanctum::actingAs($cliente);
        $this->getJson('/api/admin/whatsapp/messages')->assertForbidden();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function messageEnAttente(string $waId = 'wamid.test'): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            'wa_message_id' => $waId,
            'from_phone'    => '2250707849883',
            'contact_name'  => 'Awa',
            'type'          => 'text',
            'body'          => 'Bonjour, je veux 2 eau de rose',
            'status'        => WhatsAppMessage::STATUS_PENDING,
            'received_at'   => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(string $waId, string $texte): array
    {
        return ['entry' => [['changes' => [['value' => [
            'contacts' => [['wa_id' => '2250707849883', 'profile' => ['name' => 'Awa']]],
            'messages' => [[
                'from'      => '2250707849883',
                'type'      => 'text',
                'id'        => $waId,
                'timestamp' => (string) now()->timestamp,
                'text'      => ['body' => $texte],
            ]],
        ]]]]]];
    }

    /** @param array<string, mixed> $payload */
    private function postWebhook(array $payload)
    {
        return $this->postJson('/api/whatsapp/webhook', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), self::SECRET),
        ]);
    }
}
