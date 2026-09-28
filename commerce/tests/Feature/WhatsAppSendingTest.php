<?php

namespace Tests\Feature;

use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Auth\Services\CustomerWhatsAppService;
use Tests\TestCase;

/**
 * Arbitrage texte libre / modèle approuvé.
 *
 * C'est la règle la plus coûteuse à découvrir en production : WhatsApp accepte
 * le texte libre pendant 24 h après le dernier message de la cliente, et le
 * refuse ensuite sans que l'appelant s'en aperçoive. Le service doit trancher
 * tout seul, sinon chaque nouvel appel réintroduit le bug.
 */
class WhatsAppSendingTest extends TestCase
{
    use RefreshDatabase;

    private CustomerWhatsAppService $whatsapp;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.token'           => 'jeton-de-test',
            'services.whatsapp.phone_number_id' => '111222333',
            'services.whatsapp.graph_version'   => 'v22.0',
            'services.whatsapp.templates'       => [
                'order_confirmation' => 'order_confirmation',
                'admin_new_order'    => 'admin_new_order',
                'otp_code'           => 'otp_code',
                'lang'               => 'fr',
            ],
        ]);

        $this->whatsapp = app(CustomerWhatsAppService::class);
    }

    public function test_la_fenetre_est_ouverte_apres_un_message_recent_de_la_cliente(): void
    {
        $this->messageEntrant('2250585825555', now()->subHours(3));

        $this->assertTrue($this->whatsapp->sessionIsOpen('0585825555'));
    }

    public function test_la_fenetre_se_referme_apres_vingt_quatre_heures(): void
    {
        $this->messageEntrant('2250585825555', now()->subHours(25));

        $this->assertFalse($this->whatsapp->sessionIsOpen('0585825555'));
    }

    public function test_le_numero_est_reconnu_quel_que_soit_son_format(): void
    {
        // Meta livre 2250585825555, la base garde souvent 05 85 82 55 55.
        $this->messageEntrant('2250585825555', now()->subHour());

        $this->assertTrue($this->whatsapp->sessionIsOpen('+225 05 85 82 55 55'));
        $this->assertTrue($this->whatsapp->sessionIsOpen('0585825555'));
    }

    public function test_fenetre_ouverte_le_texte_complet_part(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);
        $this->messageEntrant('2250585825555', now()->subHour());

        $envoye = $this->whatsapp->sendSessionOrTemplate(
            '0585825555',
            'Récapitulatif complet de votre commande',
            'order_confirmation',
            ['Awa', 'ORD-2026-00001', '15 000 F CFA'],
        );

        $this->assertTrue($envoye);
        Http::assertSent(function (Request $r) {
            return $r['type'] === 'text'
                && $r['text']['body'] === 'Récapitulatif complet de votre commande';
        });
    }

    public function test_fenetre_fermee_le_modele_prend_le_relais(): void
    {
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);

        // Aucun message entrant : la cliente a commandé sur le site sans jamais
        // écrire au WhatsApp de la boutique — le cas le plus courant.
        $envoye = $this->whatsapp->sendSessionOrTemplate(
            '0585825555',
            'Récapitulatif complet de votre commande',
            'order_confirmation',
            ['Awa', 'ORD-2026-00001', '15 000 F CFA'],
        );

        $this->assertTrue($envoye);
        Http::assertSent(function (Request $r) {
            return $r['type'] === 'template'
                && $r['template']['name'] === 'order_confirmation'
                && $r['template']['language']['code'] === 'fr'
                && $r['template']['components'][0]['parameters'][1]['text'] === 'ORD-2026-00001';
        });
    }

    public function test_sans_modele_configure_rien_n_est_envoye(): void
    {
        Http::fake();
        config(['services.whatsapp.templates.order_confirmation' => null]);

        $envoye = $this->whatsapp->sendSessionOrTemplate('0585825555', 'Texte', 'order_confirmation');

        $this->assertFalse($envoye);
        Http::assertNothingSent();
    }

    public function test_un_refus_de_meta_remonte_faux_sans_lever_d_exception(): void
    {
        // Le canal WhatsApp est un confort : l'email reste le secours. Une
        // exception ici ferait échouer la création de commande elle-même.
        Http::fake(['*' => Http::response(['error' => ['code' => 131047]], 400)]);

        $this->assertFalse($this->whatsapp->sendText('0585825555', 'Bonjour'));
    }

    private function messageEntrant(string $from, \DateTimeInterface $recu): void
    {
        WhatsAppMessage::create([
            'wa_message_id' => 'wamid.' . uniqid(),
            'from_phone'    => $from,
            'type'          => 'text',
            'body'          => 'Bonjour',
            'status'        => WhatsAppMessage::STATUS_PENDING,
            'received_at'   => $recu,
        ]);
    }
}
